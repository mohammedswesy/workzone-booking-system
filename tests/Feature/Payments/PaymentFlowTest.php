<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Payments\MarkBookingPaid;
use App\Services\Payments\PaypalPaymentGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

function pendingBookingFor(User $user, ?Workspace $workspace = null): Booking
{
    $workspace ??= Workspace::factory()->create();

    return Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_price' => '150.00',
    ]);
}

it('accepts manual proof upload and confirms payment via owner', function () {
    Storage::fake('local');

    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();
    $booking = pendingBookingFor($user, $workspace);

    $this->actingAs($user)
        ->post(route('user.payments.manual.store', $booking), [
            'method' => 'bank_transfer',
            'proof' => UploadedFile::fake()->create('receipt.jpg', 200, 'image/jpeg'),
        ])
        ->assertRedirect();

    $payment = Payment::where('booking_id', $booking->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe(PaymentProvider::Manual)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Pending);

    $this->actingAs($owner)
        ->post(route('payments.manual.confirm', $payment))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('marks booking paid idempotently', function () {
    $booking = pendingBookingFor(User::factory()->userRole()->create());
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'manual-idem-1',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    $service = app(MarkBookingPaid::class);
    $first = $service->handle($payment);
    $second = $service->handle($payment->fresh());

    expect($first->status)->toBe(PaymentStatus::Paid)
        ->and($second->status)->toBe(PaymentStatus::Paid)
        ->and(Payment::where('booking_id', $booking->id)->where('status', PaymentStatus::Paid)->count())->toBe(1)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('initiates paypal payment with faked client and captures on return', function () {
    config()->set('payments.providers.paypal.enabled', true);
    config()->set('paypal.mode', 'sandbox');
    config()->set('paypal.sandbox.client_id', 'test-client-id');
    config()->set('paypal.sandbox.client_secret', 'test-client-secret');

    $user = User::factory()->userRole()->create();
    $booking = pendingBookingFor($user);

    $client = Mockery::mock(PayPalClient::class);
    $client->shouldReceive('createOrder')->once()->andReturn([
        'id' => 'ORDER-123',
        'links' => [
            ['rel' => 'approve', 'href' => 'https://paypal.test/approve/ORDER-123'],
        ],
    ]);
    $client->shouldReceive('capturePaymentOrder')->once()->with('ORDER-123')->andReturn([
        'status' => 'COMPLETED',
    ]);

    $this->app->instance(
        PaypalPaymentGateway::class,
        new PaypalPaymentGateway(app(MarkBookingPaid::class), $client)
    );

    $this->actingAs($user)
        ->post(route('user.payments.paypal.store', $booking))
        ->assertRedirect('https://paypal.test/approve/ORDER-123');

    $payment = Payment::where('booking_id', $booking->id)->first();
    expect($payment->metadata['paypal_order_id'])->toBe('ORDER-123')
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Pending);

    $this->actingAs($user)
        ->get(route('payments.paypal.return', ['token' => 'ORDER-123']))
        ->assertRedirect(route('user.bookings.show', $booking));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('rejects paypal initiation when credentials are not configured', function () {
    config()->set('payments.providers.paypal.enabled', true);
    config()->set('paypal.mode', 'sandbox');
    config()->set('paypal.sandbox.client_id', '');
    config()->set('paypal.sandbox.client_secret', '');

    $user = User::factory()->userRole()->create();
    $booking = pendingBookingFor($user);

    $this->actingAs($user)
        ->post(route('user.payments.paypal.store', $booking))
        ->assertStatus(422);

    expect(Payment::where('booking_id', $booking->id)->count())->toBe(0);
});

it('handles paypal webhooks idempotently when already paid', function () {
    config()->set('payments.providers.paypal.enabled', true);
    config()->set('payments.providers.paypal.webhook_id', null);

    $booking = pendingBookingFor(User::factory()->userRole()->create());
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Paypal,
        'reference' => 'paypal-ref-1',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
        'metadata' => ['paypal_order_id' => 'ORDER-999'],
    ]);

    $this->postJson(route('webhooks.paypal'), [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => [
            'id' => 'ORDER-999',
            'purchase_units' => [
                ['reference_id' => 'paypal-ref-1'],
            ],
        ],
    ], [
        'X-PayPal-Webhook-Valid' => '1',
    ])->assertOk()
        ->assertJsonPath('status', 'paid');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});
