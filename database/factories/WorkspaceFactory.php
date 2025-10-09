<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Workspace;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->company . ' Workspace',
            'location'       => $this->faker->city,
            'capacity'       => $this->faker->numberBetween(5, 100),
            'price_per_hour' => $this->faker->numberBetween(10, 100),
            'image_url'      => $this->faker->imageUrl(640, 480, 'business', true),
            'owner_id'       => 1, // مؤقتًا، أو اربط بـ User موجود (مثلاً Owner)
        ];
    }
}
