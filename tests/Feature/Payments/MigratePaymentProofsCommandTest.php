<?php

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('reports when no public payment proofs exist', function () {
    Storage::fake('public');
    Storage::fake('local');

    Artisan::call('payments:migrate-proofs-to-private');

    expect(Artisan::output())->toContain('No public-disk payment proof files found.');
});

it('moves legacy public payment proofs to the private disk', function () {
    Storage::fake('public');
    Storage::fake('local');

    $path = 'payment-proofs/legacy.jpg';
    Storage::disk('public')->put($path, 'legacy-bytes');

    $booking = Booking::factory()->create();
    Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'manual-legacy-proof',
        'amount' => $booking->total_price,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'proof_path' => $path,
    ]);

    Artisan::call('payments:migrate-proofs-to-private');

    expect(Artisan::output())->toContain('Found 1 payment proof file')
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(Storage::disk('local')->get($path))->toBe('legacy-bytes');
});
