<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores payment proofs on the private local disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $method = PlatformPaymentMethod::factory()->create(['is_active' => true]);
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
            'platform_payment_method_id' => $method->id,
            'transfer_reference' => 'PROOF-STORE-1',
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect();

    $payment = Payment::where('booking_id', $booking->id)->firstOrFail();

    expect($payment->proof_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($payment->proof_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($payment->proof_path))->toBeFalse()
        ->and($payment->proof_url)->toBe(route('payments.proof.show', $payment));
});

it('allows booking user and admin to download payment proof but not the owner', function () {
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

    $path = 'payment-proofs/authorized.jpg';
    Storage::disk('local')->put($path, 'fake-image-bytes');

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'manual-proof-auth-1',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'proof_path' => $path,
    ]);

    $this->actingAs($booker)
        ->get(route('payments.proof.show', $payment))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('payments.proof.show', $payment))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('payments.proof.show', $payment))
        ->assertOk();
});

it('forbids guests and unrelated users from downloading payment proof', function () {
    Storage::fake('local');

    $owner = User::factory()->owner()->create();
    $otherOwner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $booker = User::factory()->userRole()->create();
    $stranger = User::factory()->userRole()->create();
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
        'reference' => 'manual-proof-forbid-1',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'proof_path' => $path,
    ]);

    $this->get(route('payments.proof.show', $payment))
        ->assertRedirect(route('login'));

    $this->actingAs($stranger)
        ->get(route('payments.proof.show', $payment))
        ->assertForbidden();

    $this->actingAs($otherOwner)
        ->get(route('payments.proof.show', $payment))
        ->assertForbidden();

    $publicStorage = $this->get('/storage/'.$path);
    expect($publicStorage->status())->toBeIn([403, 404]);
});
