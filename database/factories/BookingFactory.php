<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\Role;
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
        $hours = $this->faker->numberBetween(1, 8);
        $workspace = Workspace::inRandomOrder()->first() ?? Workspace::factory()->create();
        $user = User::where('role', Role::User)->inRandomOrder()->first()
            ?? User::factory()->userRole()->create();

        return [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'hours' => $hours,
            'total_price' => $workspace->price_per_hour * $hours,
            'status' => $this->faker->randomElement(BookingStatus::cases()),
        ];
    }
}
