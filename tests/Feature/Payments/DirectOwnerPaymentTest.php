<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('allows publishing without per-workspace payment setup', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('owner.workspaces.store'), [
            'name' => 'Published Free Of Pay Setup',
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
});

it('shows platform payment methods to the booking user', function () {
    $method = PlatformPaymentMethod::factory()->create([
        'label' => 'Platform IBAN',
        'account_identifier' => 'PS-PLATFORM',
        'is_active' => true,
    ]);
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $booker = User::factory()->userRole()->create();
    $stranger = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $booker->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->actingAs($booker)
        ->get(route('user.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Bookings/Show')
            ->where('platformPaymentMethods.0.id', $method->id)
            ->where('platformPaymentMethods.0.account_identifier', 'PS-PLATFORM')
        );

    $this->actingAs($stranger)
        ->get(route('user.bookings.show', $booking))
        ->assertForbidden();
});

it('forbids owners from confirming payments', function () {
    Storage::fake('local');

    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'manual-owner-forbid-confirm',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'proof_path' => 'payment-proofs/x.jpg',
    ]);

    withPasswordConfirmed($this->actingAs($owner))
        ->post(route('payments.manual.confirm', $payment), [
            'received_amount' => $payment->amount,
        ])
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('exposes rejection reason to the booking user after admin rejects proof', function () {
    Storage::fake('local');

    $method = PlatformPaymentMethod::factory()->create(['is_active' => true]);
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
        ->post(route('user.payments.manual.store', $booking), [
            'platform_payment_method_id' => $method->id,
            'transfer_reference' => 'REJ-REF-1',
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect();

    $payment = Payment::where('booking_id', $booking->id)->firstOrFail();

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('payments.manual.reject', $payment), [
            'reason' => 'Amount does not match the booking total.',
        ])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->rejection_reason)->toBe('Amount does not match the booking total.')
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);

    $this->actingAs($user)
        ->get(route('user.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('booking.payments.0.rejection_reason', 'Amount does not match the booking total.')
            ->where('booking.payment_status', PaymentStatus::Unpaid->value)
        );
});
