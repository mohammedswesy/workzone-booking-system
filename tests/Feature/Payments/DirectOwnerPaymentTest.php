<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('requires payment instructions and methods before a workspace can be published', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('owner.workspaces.store'), [
            'name' => 'No Pay Info',
            'location' => 'Gaza',
            'capacity' => 10,
            'price_per_hour' => 20,
            'status' => WorkspaceStatus::Published->value,
            'payment_instructions' => '',
            'payment_methods' => [],
        ])
        ->assertSessionHasErrors(['payment_instructions', 'payment_methods']);

    $this->actingAs($owner)
        ->post(route('owner.workspaces.store'), [
            'name' => 'Draft Without Pay',
            'location' => 'Gaza',
            'capacity' => 10,
            'price_per_hour' => 20,
            'status' => WorkspaceStatus::Draft->value,
        ])
        ->assertRedirect(route('owner.workspaces.index'));
});

it('shows payment instructions only to the booking user', function () {
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'payment_instructions' => 'SECRET-IBAN-ONLY-FOR-BOOKER',
        'payment_methods' => ['bank_transfer', 'cash'],
    ]);
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
            ->where('booking.workspace.payment_instructions', 'SECRET-IBAN-ONLY-FOR-BOOKER')
        );

    $this->actingAs($stranger)
        ->get(route('user.bookings.show', $booking))
        ->assertForbidden();
});

it('forbids owner A from confirming payment for owner B booking', function () {
    Storage::fake('local');

    $ownerA = User::factory()->owner()->create();
    $ownerB = User::factory()->owner()->create();
    $workspaceB = Workspace::factory()->create(['owner_id' => $ownerB->id]);
    $user = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspaceB->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'manual-cross-owner-1',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'proof_path' => 'payment-proofs/x.jpg',
    ]);

    $this->actingAs($ownerA)
        ->post(route('payments.manual.confirm', $payment))
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('exposes rejection reason to the booking user after owner rejects proof', function () {
    Storage::fake('local');

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
            'method' => 'bank_transfer',
            'proof' => UploadedFile::fake()->create('receipt.jpg', 200, 'image/jpeg'),
        ])
        ->assertRedirect();

    $payment = Payment::where('booking_id', $booking->id)->firstOrFail();

    $this->actingAs($owner)
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
