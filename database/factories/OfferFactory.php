<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $starts = now()->subDay();

        return [
            'owner_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'venue_id' => null,
            'title' => $this->faker->randomElement(['Launch week', 'Student deal', 'Afternoon focus']),
            'discount_percent' => $this->faker->numberBetween(5, 25),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addWeeks(2),
            'is_active' => true,
        ];
    }
}
