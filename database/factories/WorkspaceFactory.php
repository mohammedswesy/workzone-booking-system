<?php

namespace Database\Factories;

use App\Enums\BookingMode;
use App\Enums\WorkspaceStatus;
use App\Models\Location;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = $this->faker->company().' Workspace';

        return [
            'name' => $name,
            // Leave slug null so model boot generates it from the final name.
            'location' => $this->faker->city(),
            'location_id' => Location::factory(),
            'description' => $this->faker->paragraph(),
            'capacity' => $this->faker->numberBetween(5, 100),
            'booking_mode' => BookingMode::Whole,
            'price_per_hour' => $this->faker->numberBetween(10, 100),
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'image_url' => $this->faker->imageUrl(640, 480, 'business', true),
            'status' => WorkspaceStatus::Published,
            'featured' => false,
            'payment_instructions' => "Bank transfer to owner account.\nIBAN: PS00 EXAMPLE 0000 0000\nMention booking ID in the note.",
            'payment_methods' => ['bank_transfer', 'wallet', 'cash'],
            'owner_id' => User::factory()->owner(),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['featured' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => WorkspaceStatus::Draft]);
    }
}
