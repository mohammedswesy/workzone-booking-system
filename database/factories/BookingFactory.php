<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;

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
        $user = User::where('role','user')->inRandomOrder()->first() ?? User::factory()->create(['role' => 'user']);

        return [
            'user_id'      => $user->id,
            'workspace_id' => $workspace->id,
            'hours'        => $hours,
            'total_price'  => $workspace->price_per_hour * $hours,
            'status' => $this->faker->randomElement(['pending','paid','cancelled']),
        ];
    }
}
