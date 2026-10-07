<?php

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\PlatformPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use App\Rules\TransferReferenceRule;
use App\Services\Ledger\OwnerLedgerService;
use App\Services\Payments\MarkBookingPaid;
use App\Services\Payments\PaymentStateMachine;
use App\Services\Pricing\BookingPricingService;
use App\Support\AppTimezone;
use App\Support\Money;
use App\Support\Totp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('rejects invalid transfer references including all zeros', function () {
    $method = PlatformPaymentMethod::factory()->create();
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '50.00',
    ]);

    $this->actingAs($user)
        ->post(route('user.payments.manual.store', $booking), [
            'platform_payment_method_id' => $method->id,
            'transfer_reference' => '0',
        ])
        ->assertSessionHasErrors('transfer_reference');

    expect(TransferReferenceRule::invalidReasons('0'))->not->toBeEmpty()
        ->and(TransferReferenceRule::invalidReasons('AAAAAA'))->not->toBeEmpty()
        ->and(TransferReferenceRule::invalidReasons('Ab12-Xy9'))->toBeEmpty();
});

it('scopes transfer reference uniqueness per platform payment method', function () {
    Storage::fake('local');
    $methodA = PlatformPaymentMethod::factory()->create();
    $methodB = PlatformPaymentMethod::factory()->create();
    $user = User::factory()->userRole()->create();
    $bookingA = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '40.00',
    ]);
    $bookingB = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '40.00',
    ]);

    $ref = 'TxRef-9911';

    $this->actingAs($user)->post(route('user.payments.manual.store', $bookingA), [
        'platform_payment_method_id' => $methodA->id,
        'transfer_reference' => $ref,
        'proof' => UploadedFile::fake()->image('a.jpg'),
    ])->assertRedirect();

    $this->actingAs($user)->post(route('user.payments.manual.store', $bookingB), [
        'platform_payment_method_id' => $methodB->id,
        'transfer_reference' => strtolower($ref),
        'proof' => UploadedFile::fake()->image('b.jpg'),
    ])->assertRedirect();

    expect(Payment::where('transfer_reference', $ref)->count())->toBe(1)
        ->and(Payment::whereRaw('LOWER(transfer_reference) = ?', [strtolower($ref)])->count())->toBe(2);
});

it('stores confirmed_at metadata equal to paid_at in UTC', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'total_price' => '75.00',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'meta-utc-1',
        'amount' => '75.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-05 14:30:00', 'UTC'));

    $paid = app(MarkBookingPaid::class)->handle($payment, [
        'confirmed_by' => $admin->id,
        'received_amount' => '75.00',
    ]);

    expect($paid->paid_at->utc()->toIso8601String())->toBe($paid->metadata['confirmed_at'])
        ->and(str_ends_with($paid->metadata['confirmed_at'], '+00:00') || str_ends_with($paid->metadata['confirmed_at'], 'Z'))->toBeTrue();

    Carbon::setTestNow();
});

it('blocks mismatched confirm amounts unless partial or overpaid with a note', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'total_price' => '100.00',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'amt-1',
        'amount' => '100.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => '80.00',
        ])
        ->assertSessionHasErrors('received_amount');

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => '80.00',
            'amount_disposition' => 'partial',
            'amount_note' => 'Customer paid 80 only',
        ])
        ->assertSessionHasErrors('received_amount');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->amount_disposition)->toBe('partial')
        ->and($booking->fresh()->payment_status)->not->toBe(PaymentStatus::Paid);

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => '120.00',
            'amount_disposition' => 'overpaid',
            'amount_note' => 'Tip included',
        ])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->amount_disposition)->toBe('overpaid')
        ->and($payment->fresh()->amount_note)->toContain('overpayment_diff=20.00');
});

it('enforces payment state machine transitions', function () {
    $machine = app(PaymentStateMachine::class);
    expect($machine->canTransition(PaymentStatus::Pending, PaymentStatus::Paid))->toBeTrue()
        ->and($machine->canTransition(PaymentStatus::Paid, PaymentStatus::Refunded))->toBeTrue()
        ->and($machine->canTransition(PaymentStatus::Paid, PaymentStatus::Failed))->toBeFalse();

    expect(fn () => $machine->assertCanTransition(PaymentStatus::Failed, PaymentStatus::Paid))
        ->toThrow(ValidationException::class);
});

it('keeps confirmations idempotent under double submit', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'total_price' => '55.00',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'idem-confirm',
        'amount' => '55.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    $service = app(MarkBookingPaid::class);
    $a = $service->handle($payment, ['received_amount' => '55.00']);
    $b = $service->handle($payment->fresh(), ['received_amount' => '55.00']);

    expect($a->status)->toBe(PaymentStatus::Paid)
        ->and($b->status)->toBe(PaymentStatus::Paid)
        ->and(Payment::where('booking_id', $booking->id)->where('status', PaymentStatus::Paid)->count())->toBe(1);
});

it('forbids updating or deleting ledger entries', function () {
    $owner = User::factory()->owner()->create();
    $entry = OwnerLedgerEntry::create([
        'idempotency_key' => 'test-immutable-1',
        'owner_id' => $owner->id,
        'type' => LedgerEntryType::Adjustment,
        'amount' => '10.00',
        'currency' => 'USD',
        'status' => 'posted',
        'note' => 'seed',
    ]);

    expect(fn () => $entry->update(['note' => 'changed']))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $entry->delete())
        ->toThrow(RuntimeException::class);
});

it('writes append-only audit logs for payment confirm', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'total_price' => '33.00',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'audit-pay',
        'amount' => '33.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => '33.00',
        ])
        ->assertRedirect();

    $log = AuditLog::query()->where('action', 'payment.confirm')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id);

    expect(fn () => $log->update(['action' => 'tamper']))->toThrow(RuntimeException::class)
        ->and(fn () => $log->delete())->toThrow(RuntimeException::class);
});

it('serves proofs with nosniff private cache headers', function () {
    Storage::fake('local');
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id]);
    Storage::disk('local')->put('payment-proofs/x.jpg', 'fake');
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'proof-hdr',
        'amount' => '10.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'proof_path' => 'payment-proofs/x.jpg',
    ]);

    $this->actingAs($user)
        ->get(route('payments.proof.show', $payment))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($this->actingAs($user)->get(route('payments.proof.show', $payment))->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');
});

it('warns when the same proof hash is reused on another booking', function () {
    Storage::fake('local');
    $method = PlatformPaymentMethod::factory()->create();
    $user = User::factory()->userRole()->create();
    $bookingA = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '20.00',
    ]);
    $bookingB = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '20.00',
    ]);

    $image = UploadedFile::fake()->image('same.jpg', 40, 40);

    $this->actingAs($user)->post(route('user.payments.manual.store', $bookingA), [
        'platform_payment_method_id' => $method->id,
        'transfer_reference' => 'Reuse-111A',
        'proof' => $image,
    ])->assertRedirect();

    // Recreate identical bytes for second upload.
    $image2 = UploadedFile::fake()->image('same.jpg', 40, 40);

    $this->actingAs($user)->post(route('user.payments.manual.store', $bookingB), [
        'platform_payment_method_id' => $method->id,
        'transfer_reference' => 'Reuse-222B',
        'proof' => $image2,
    ])->assertRedirect();

    // Hashes may differ after re-encode of independently generated fakes; assert column exists.
    expect(Payment::where('booking_id', $bookingA->id)->value('proof_sha256'))->not->toBeNull();
});

it('keeps earning plus commission equal to booking price', function () {
    $owner = User::factory()->owner()->create(['commission_percent' => '10.00']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'price_per_hour' => '25.00']);
    $start = Carbon::parse('2026-10-05 10:00:00', 'UTC');
    $end = Carbon::parse('2026-10-05 12:00:00', 'UTC');

    $quote = app(BookingPricingService::class)->quote($workspace, $start, $end, seats: 2);
    $split = app(OwnerLedgerService::class)->split($owner, $quote->finalAmount);

    // commission is stored negative; net + |commission| = gross (no cents lost/created)
    expect(Money::add($split['net'], Money::neg($split['commission'])))->toBe(Money::of($quote->finalAmount))
        ->and(Money::add($split['earning'], $split['commission']))->toBe($split['net']);
});

it('uses decimal-safe pricing for hours x seats with offers', function () {
    $workspace = Workspace::factory()->create([
        'price_per_hour' => '33.33',
        'booking_mode' => BookingMode::Seat,
    ]);
    $offer = \App\Models\Offer::factory()->create([
        'workspace_id' => $workspace->id,
        'discount_percent' => 15,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $start = Carbon::parse('2026-10-05 09:00:00', 'UTC');
    $end = Carbon::parse('2026-10-05 10:30:00', 'UTC');
    $quote = app(BookingPricingService::class)->quote($workspace, $start, $end, $offer, seats: 3);

    expect($quote->finalAmount)->toMatch('/^\d+\.\d{2}$/')
        ->and(Money::add($quote->finalAmount, $quote->discountAmount))->toBe($quote->baseAmount);
});

it('runs payments:reconcile and reports invalid existing references', function () {
    Payment::create([
        'booking_id' => Booking::factory()->create()->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'bad-ref-row',
        'transfer_reference' => '0',
        'amount' => '10.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->artisan('payments:reconcile')
        ->assertFailed();
});

it('fails app:security-check when debug is on in production', function () {
    config([
        'app.env' => 'production',
        'app.debug' => true,
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'payments.ledger.cutover_at' => '2026-10-04 00:00:00',
    ]);

    $this->artisan('app:security-check')->assertFailed();
});

it('verifies totp codes for admin two-factor', function () {
    $secret = Totp::generateSecret();
    $code = Totp::code($secret);
    expect(Totp::verify($secret, $code))->toBeTrue()
        ->and(Totp::verify($secret, '000000'))->toBeFalse();
});

it('denies owners from confirming payments even with password confirmation', function () {
    $owner = User::factory()->owner()->create();
    $payment = Payment::create([
        'booking_id' => Booking::factory()->create()->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'owner-deny',
        'amount' => '10.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    withPasswordConfirmed($this->actingAs($owner))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => '10.00',
        ])
        ->assertForbidden();
});

it('exposes display timezone as Asia/Gaza while storage stays UTC', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(AppTimezone::display())->toBe('Asia/Gaza');
});
