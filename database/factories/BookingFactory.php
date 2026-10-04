<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $hours = $this->faker->numberBetween(1, 4);
        $workspace = Workspace::factory()->create();
        $user = User::factory()->userRole()->create();
        $start = now()->addDay()->setTime(10, 0);

        return [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours($hours),
            'hours' => $hours,
            'seats' => 1,
            'total_price' => $workspace->price_per_hour * $hours,
            'status' => BookingStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ];
    }
}
