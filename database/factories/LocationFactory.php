<?php

namespace Database\Factories;

use App\Models\Location;
use App\Support\DemoCityCoordinates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $place = DemoCityCoordinates::random();
        // Tiny jitter so fixtures do not collide, still inside the city box.
        $lat = round($place['lat'] + $this->faker->randomFloat(5, -0.015, 0.015), 7);
        $lng = round($place['lng'] + $this->faker->randomFloat(5, -0.015, 0.015), 7);

        return [
            'name' => $place['name'],
            'address' => $place['address'],
            'city' => $place['city'],
            'lat' => $lat,
            'lng' => $lng,
        ];
    }
}
