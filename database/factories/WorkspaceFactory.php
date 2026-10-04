<?php

namespace Database\Factories;

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
        return [
            'name' => $this->faker->company().' Workspace',
            'location' => $this->faker->city(),
            'capacity' => $this->faker->numberBetween(5, 100),
            'price_per_hour' => $this->faker->numberBetween(10, 100),
            'image_url' => $this->faker->imageUrl(640, 480, 'business', true),
            'owner_id' => User::factory()->owner(),
        ];
    }
}
