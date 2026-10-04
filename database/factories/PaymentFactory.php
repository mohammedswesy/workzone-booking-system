<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $booking = Booking::factory()->create();

        return [
            'booking_id' => $booking->id,
            'provider' => PaymentProvider::Manual,
            'reference' => 'manual-'.$this->faker->unique()->numerify('########'),
            'amount' => $booking->total_price,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
            'proof_path' => null,
            'rejection_reason' => null,
            'metadata' => ['method' => 'bank_transfer'],
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
