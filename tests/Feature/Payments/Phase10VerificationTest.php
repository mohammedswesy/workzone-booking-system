<?php

use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\PlatformPaymentMethod;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ledger\OwnerLedgerService;
use App\Services\Payments\MarkBookingPaid;
use App\Services\Payouts\OwnerPayoutService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function verificationOwnerBooking(string $total = '100.00', array $bookingAttrs = [], array $paymentAttrs = []): array
{
    $owner = User::factory()->owner()->create([
        'payout_method' => 'bank_transfer',
        'payout_account_holder' => 'Owner',
        'payout_account_identifier' => 'PS-OWNER-VER',
        'commission_percent' => null,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create(array_merge([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total_price' => $total,
    ], $bookingAttrs));

    $payment = Payment::create(array_merge([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'ver-'.uniqid(),
        'amount' => $total,
        'currency' => 'USD',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
        'proof_path' => 'payment-proofs/ver.jpg',
    ], $paymentAttrs));

    return compact('owner', 'workspace', 'user', 'booking', 'payment');
}

it('does not create ledger earnings for payments confirmed before the cutover', function () {
    PlatformSetting::setValue('commission_percent', '10.00');
    config(['payments.ledger.cutover_at' => '2026-10-04 00:00:00']);

    ['owner' => $owner, 'booking' => $booking] = verificationOwnerBooking('80.00', [], [
        'paid_at' => '2026-10-03 23:59:59',
    ]);

    $ledger = app(OwnerLedgerService::class);
    $ledger->postCompletionEntries($booking->fresh());

    expect(OwnerLedgerEntry::query()->where('booking_id', $booking->id)->count())->toBe(0)
        ->and($ledger->balancesFor($owner)['balance'])->toBe('0.00')
        ->and($ledger->balancesFor($owner)['pending'])->toBe('0.00');
});

it('creates ledger earnings only for payments confirmed on or after cutover', function () {
    PlatformSetting::setValue('commission_percent', '10.00');
    config(['payments.ledger.cutover_at' => '2026-10-04 00:00:00']);

    ['owner' => $owner, 'booking' => $booking] = verificationOwnerBooking('100.00', [], [
        'paid_at' => '2026-10-04 00:00:00',
    ]);

    app(OwnerLedgerService::class)->postCompletionEntries($booking->fresh());

    expect(OwnerLedgerEntry::query()->where('booking_id', $booking->id)->where('type', LedgerEntryType::Earning)->count())->toBe(1)
        ->and(OwnerLedgerEntry::query()->where('booking_id', $booking->id)->where('type', LedgerEntryType::Commission)->count())->toBe(1)
        ->and(app(OwnerLedgerService::class)->balancesFor($owner)['balance'])->toBe('90.00');
});

it('posts exactly one earning and one commission regardless of order and repeats', function () {
    PlatformSetting::setValue('commission_percent', '10.00');
    config(['payments.ledger.cutover_at' => '2026-10-04 00:00:00']);

    $owner = User::factory()->owner()->create(['commission_percent' => null]);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Completed,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '50.00',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'idem-'.uniqid(),
        'amount' => '50.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    // Completed first, then paid (MarkBookingPaid must not demote Completed).
    app(MarkBookingPaid::class)->handle($payment, ['confirmed_by' => $admin->id]);
    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);

    $ledger = app(OwnerLedgerService::class);
    $ledger->postCompletionEntries($booking->fresh());
    $ledger->postCompletionEntries($booking->fresh());

    // Flip-flop completed → confirmed → completed
    $booking->update(['status' => BookingStatus::Confirmed]);
    $booking->update(['status' => BookingStatus::Completed]);
    $ledger->postCompletionEntries($booking->fresh());

    expect(OwnerLedgerEntry::query()->where('booking_id', $booking->id)->where('type', LedgerEntryType::Earning)->count())->toBe(1)
        ->and(OwnerLedgerEntry::query()->where('booking_id', $booking->id)->where('type', LedgerEntryType::Commission)->count())->toBe(1)
        ->and(OwnerLedgerEntry::query()->where('idempotency_key', 'earning:'.$booking->id)->count())->toBe(1)
        ->and(OwnerLedgerEntry::query()->where('idempotency_key', 'commission:'.$booking->id)->count())->toBe(1);
});

it('allows negative ledger balance after refund post-payout but keeps available at zero', function () {
    PlatformSetting::setValue('commission_percent', '0');
    config(['payments.ledger.cutover_at' => '2026-10-04 00:00:00']);

    ['owner' => $owner, 'booking' => $booking] = verificationOwnerBooking('60.00');
    $admin = User::factory()->admin()->create();
    $ledger = app(OwnerLedgerService::class);
    $payouts = app(OwnerPayoutService::class);

    $ledger->postCompletionEntries($booking->fresh());
    expect($ledger->balancesFor($owner)['available'])->toBe('60.00');

    $payout = $payouts->request($owner, '60.00');
    $payouts->approve($payout, '60.00', null, $admin);
    $payouts->markPaid($payout->fresh(), 'PAY-OUT-1', now()->toDateString(), null, $admin, 'bank_transfer');

    expect($ledger->balancesFor($owner)['balance'])->toBe('0.00')
        ->and($ledger->balancesFor($owner)['available'])->toBe('0.00');

    $ledger->postRefundReversal($booking->fresh());

    $balances = $ledger->balancesFor($owner);
    expect($balances['balance'])->toBe('-60.00')
        ->and($balances['available'])->toBe('0.00')
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded);

    expect(fn () => $payouts->request($owner->fresh(), '0.01'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('never includes proof path or url in owner booking inertia props', function () {
    Storage::fake('local');
    ['owner' => $owner, 'booking' => $booking, 'payment' => $payment] = verificationOwnerBooking(
        '25.00',
        ['status' => BookingStatus::Pending, 'payment_status' => PaymentStatus::Pending],
        ['status' => PaymentStatus::Pending, 'paid_at' => null, 'proof_path' => 'payment-proofs/secret-owner.jpg'],
    );

    $this->actingAs($owner)
        ->get(route('owner.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Bookings/Show')
            ->has('booking.payments', 1)
            ->missing('booking.payments.0.proof_path')
            ->missing('booking.payments.0.proof_url')
            ->missing('booking.payments.0.transfer_reference')
            ->where('booking.payments.0.status', PaymentStatus::Pending->value)
        );

    expect($payment->proof_path)->toBe('payment-proofs/secret-owner.jpg');
});

it('shows a clear message when no active platform methods and warns on admin dashboard', function () {
    PlatformPaymentMethod::query()->delete();

    $owner = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->actingAs($user)
        ->get(route('user.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('platformPaymentMethods', [])
            ->where('booking.workspace.payment_details_ready', false)
        );

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('platformMethodsMissing', true)
            ->where('stats.active_platform_methods', 0)
        );
});

it('lists the oldest pending proofs first on the admin dashboard', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $olderBooking = Booking::factory()->create(['workspace_id' => $workspace->id]);
    $newerBooking = Booking::factory()->create(['workspace_id' => $workspace->id]);

    $older = Payment::create([
        'booking_id' => $olderBooking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'old-proof',
        'amount' => '10.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    $newer = Payment::create([
        'booking_id' => $newerBooking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'new-proof',
        'amount' => '20.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('oldestPendingProofs.0.id', $older->id)
            ->where('oldestPendingProofs.1.id', $newer->id)
        );
});

it('accepts a user payment submission only when an active platform method exists', function () {
    Storage::fake('local');
    PlatformPaymentMethod::query()->delete();

    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->actingAs($user)
        ->post(route('user.payments.manual.store', $booking), [
            'platform_payment_method_id' => 999,
            'transfer_reference' => 'NO-METHOD',
            'proof' => UploadedFile::fake()->image('r.jpg'),
        ])
        ->assertSessionHasErrors('platform_payment_method_id');
});
