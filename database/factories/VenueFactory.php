<?php

namespace Database\Factories;

use App\Enums\VenueStatus;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $name = $this->faker->company().' Hub';

        return [
            'owner_id' => User::factory()->owner(),
            'name' => $name,
            'slug' => Venue::ensureSlugHasNonDigit(Str::slug($name).'-'.Str::lower(Str::random(4))),
            'description' => $this->faker->paragraph(),
            'location_id' => Location::factory(),
            'address' => $this->faker->streetAddress(),
            'status' => VenueStatus::Published,
            'featured' => false,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['featured' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => VenueStatus::Draft]);
    }
}
