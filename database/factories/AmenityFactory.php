<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Amenity>
 */
class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'WiFi', 'Coffee', 'Parking', 'Printer', 'Meeting Room', 'AC',
        ]).' '.$this->faker->unique()->numerify('##');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => null,
        ];
    }
}
