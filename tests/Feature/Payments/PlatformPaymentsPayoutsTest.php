<?php

use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PlatformPaymentMethod;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ledger\OwnerLedgerService;
use App\Services\Payouts\OwnerPayoutService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function seedPlatformBankMethod(): PlatformPaymentMethod
{
    return PlatformPaymentMethod::factory()->create([
        'type' => 'bank_transfer',
        'label' => 'WorkZone Bank',
        'account_holder' => 'WorkZone',
        'account_identifier' => 'PS00PLATFORM123',
        'is_active' => true,
    ]);
}

function paidCompletedBooking(User $owner, string $total = '100.00'): Booking
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();

    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total_price' => $total,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'paid-complete-'.uniqid(),
        'amount' => $total,
        'currency' => 'USD',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    return $booking;
}

it('allows only admin to manage platform payment methods', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create();

    withPasswordConfirmed($this->actingAs($owner))
        ->post(route('admin.platform-payments.store'), [
            'type' => 'bank_transfer',
            'label' => 'Owner attempt',
            'account_identifier' => 'X',
        ])
        ->assertForbidden();

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('admin.platform-payments.store'), [
            'type' => 'bank_transfer',
            'label' => 'Admin Bank',
            'account_holder' => 'WorkZone',
            'account_identifier' => 'PS11ADMIN',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(PlatformPaymentMethod::query()->where('label', 'Admin Bank')->exists())->toBeTrue();
});

it('allows publishing a workspace without payment instructions', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('owner.workspaces.store'), [
            'name' => 'No Pay Info Needed',
            'location' => 'Gaza',
            'capacity' => 10,
            'price_per_hour' => 20,
            'status' => WorkspaceStatus::Published->value,
            'payment_instructions' => '',
            'payment_methods' => [],
            'booking_mode' => 'seat',
            'opening_time' => '09:00',
            'closing_time' => '22:00',
        ])
        ->assertRedirect();

    expect(Workspace::query()->where('name', 'No Pay Info Needed')->first())
        ->status->toBe(WorkspaceStatus::Published);
});

it('accepts manual proof and confirms payment via admin only', function () {
    Storage::fake('local');
    $method = seedPlatformBankMethod();
    $owner = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '150.00',
    ]);

    $this->actingAs($user)
        ->post(route('user.payments.manual.store', $booking), [
            'platform_payment_method_id' => $method->id,
            'transfer_reference' => 'REF-UNIQUE-1',
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect();

    $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Pending);

    withPasswordConfirmed($this->actingAs($owner))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => $payment->amount,
        ])
        ->assertForbidden();

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => $payment->amount,
        ])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('forbids owners from viewing payment proofs', function () {
    Storage::fake('local');
    $owner = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $booker = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $booker->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
    ]);

    $path = 'payment-proofs/secret.jpg';
    Storage::disk('local')->put($path, 'secret-bytes');
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'manual-proof-owner-forbid',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'proof_path' => $path,
    ]);

    $this->actingAs($booker)->get(route('payments.proof.show', $payment))->assertOk();
    $this->actingAs($admin)->get(route('payments.proof.show', $payment))->assertOk();
    $this->actingAs($owner)->get(route('payments.proof.show', $payment))->assertForbidden();
});

it('posts earning and commission with decimal-safe math', function () {
    PlatformSetting::setValue('commission_percent', '10.00');
    $owner = User::factory()->owner()->create(['commission_percent' => null]);
    $booking = paidCompletedBooking($owner, '100.00');

    app(OwnerLedgerService::class)->postCompletionEntries($booking);

    $earning = OwnerLedgerEntry::query()
        ->where('booking_id', $booking->id)
        ->where('type', LedgerEntryType::Earning)
        ->first();
    $commission = OwnerLedgerEntry::query()
        ->where('booking_id', $booking->id)
        ->where('type', LedgerEntryType::Commission)
        ->first();

    expect($earning?->amount)->toBe('100.00')
        ->and($commission?->amount)->toBe('-10.00');

    $balances = app(OwnerLedgerService::class)->balancesFor($owner);
    expect($balances['balance'])->toBe('90.00')
        ->and($balances['available'])->toBe('90.00');
});

it('creates refund reversal for cancelled paid bookings with ledger entries', function () {
    PlatformSetting::setValue('commission_percent', '10.00');
    $owner = User::factory()->owner()->create();
    $booking = paidCompletedBooking($owner, '50.00');
    $ledger = app(OwnerLedgerService::class);
    $ledger->postCompletionEntries($booking);

    $ledger->postRefundReversal($booking->fresh());

    $refunds = OwnerLedgerEntry::query()
        ->where('booking_id', $booking->id)
        ->where('type', LedgerEntryType::Refund)
        ->get();

    expect($refunds)->toHaveCount(2)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($ledger->balancesFor($owner)['balance'])->toBe('0.00');
});

it('prevents payout exceeding available balance and double pay', function () {
    PlatformSetting::setValue('commission_percent', '0');
    $owner = User::factory()->owner()->create([
        'payout_method' => 'bank_transfer',
        'payout_account_holder' => 'Owner',
        'payout_account_identifier' => 'PS99OWNER',
    ]);
    $admin = User::factory()->admin()->create();
    $booking = paidCompletedBooking($owner, '40.00');
    app(OwnerLedgerService::class)->postCompletionEntries($booking);

    $service = app(OwnerPayoutService::class);

    expect(fn () => $service->request($owner, '40.01'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $payout = $service->request($owner, '40.00');
    $service->approve($payout, '40.00', null, $admin);
    $service->markPaid($payout->fresh(), 'TX-1', now()->toDateString(), null, $admin, 'bank_transfer');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Paid)
        ->and(app(OwnerLedgerService::class)->balancesFor($owner)['available'])->toBe('0.00');

    expect(fn () => $service->markPaid($payout->fresh(), 'TX-2', now()->toDateString(), null, $admin, 'bank_transfer'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('protects concurrent double-pay with row locks', function () {
    PlatformSetting::setValue('commission_percent', '0');
    $owner = User::factory()->owner()->create([
        'payout_method' => 'bank_transfer',
        'payout_account_holder' => 'Owner',
        'payout_account_identifier' => 'PS99OWNER2',
    ]);
    $admin = User::factory()->admin()->create();
    $booking = paidCompletedBooking($owner, '25.00');
    app(OwnerLedgerService::class)->postCompletionEntries($booking);

    $service = app(OwnerPayoutService::class);
    $payout = $service->request($owner, '25.00');
    $service->approve($payout, null, null, $admin);

    $errors = 0;
    try {
        DB::transaction(function () use ($service, $payout, $admin, &$errors) {
            $service->markPaid($payout->fresh(), 'RACE-1', now()->toDateString(), null, $admin, 'bank_transfer');
            try {
                $service->markPaid($payout->fresh(), 'RACE-2', now()->toDateString(), null, $admin, 'bank_transfer');
            } catch (\Illuminate\Validation\ValidationException) {
                $errors++;
            }
        });
    } catch (\Throwable) {
        $errors++;
    }

    expect(OwnerLedgerEntry::query()->where('payout_id', $payout->id)->where('type', LedgerEntryType::Payout)->count())->toBe(1)
        ->and($errors)->toBeGreaterThan(0);
});

it('restricts statement access to owning owner and admin', function () {
    $owner = User::factory()->owner()->create();
    $other = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();
    $payout = OwnerPayout::create([
        'owner_id' => $owner->id,
        'amount_requested' => '10.00',
        'amount_approved' => '10.00',
        'currency' => 'USD',
        'status' => PayoutStatus::Paid,
        'payout_method' => 'bank_transfer',
        'transfer_reference' => 'STMT-1',
        'paid_at' => now(),
    ]);

    $this->actingAs($owner)->get(route('owner.payouts.statement', $payout))->assertOk();
    $this->actingAs($admin)->get(route('admin.payouts.statement', $payout))->assertOk();
    $this->actingAs($other)->get(route('owner.payouts.statement', $payout))->assertForbidden();
});

it('forbids confirming a booking before payment is paid', function () {
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $booking = Booking::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->put(route('owner.bookings.update', $booking), ['status' => BookingStatus::Confirmed->value])
        ->assertSessionHasErrors('status');

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

it('forbids owner from viewing another owner payouts page data via statement', function () {
    $ownerA = User::factory()->owner()->create();
    $ownerB = User::factory()->owner()->create();
    $payoutB = OwnerPayout::create([
        'owner_id' => $ownerB->id,
        'amount_requested' => '5.00',
        'currency' => 'USD',
        'status' => PayoutStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($ownerA)
        ->get(route('owner.payouts.statement', $payoutB))
        ->assertForbidden();
});

it('requires unique transfer reference across the platform', function () {
    Storage::fake('local');
    $method = seedPlatformBankMethod();
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $userA = User::factory()->userRole()->create();
    $userB = User::factory()->userRole()->create();
    $bookingA = Booking::factory()->create([
        'user_id' => $userA->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);
    $bookingB = Booking::factory()->create([
        'user_id' => $userB->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->actingAs($userA)
        ->post(route('user.payments.manual.store', $bookingA), [
            'platform_payment_method_id' => $method->id,
            'transfer_reference' => 'SAME-REF',
        ])
        ->assertRedirect();

    $this->actingAs($userB)
        ->post(route('user.payments.manual.store', $bookingB), [
            'platform_payment_method_id' => $method->id,
            'transfer_reference' => 'same-ref',
        ])
        ->assertSessionHasErrors('transfer_reference');
});
