<?php

namespace Database\Factories;

use App\Enums\BookingMode;
use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Workspace = bookable unit. Ensures a parent Venue exists before insert
 * (venue_id is NOT NULL after the venues migrate).
 *
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = $this->faker->company().' Workspace';

        return [
            'name' => $name,
            'location' => $this->faker->city(),
            'location_id' => Location::factory(),
            'description' => $this->faker->paragraph(),
            'capacity' => $this->faker->numberBetween(5, 100),
            'booking_mode' => BookingMode::Whole,
            'type' => WorkspaceType::Other,
            'price_per_hour' => $this->faker->numberBetween(10, 100),
            'opening_time' => '08:00:00',
            'closing_time' => '22:00:00',
            'image_url' => null,
            'status' => WorkspaceStatus::Published,
            'featured' => false,
            'payment_instructions' => "Bank transfer to owner account.\nIBAN: PS00 EXAMPLE 0000 0000\nMention booking ID in the note.",
            'payment_methods' => ['bank_transfer', 'wallet', 'cash'],
            'owner_id' => User::factory()->owner(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Workspace $workspace) {
            if ($workspace->venue_id) {
                return;
            }

            $ownerId = $workspace->owner_id;
            if (! $ownerId) {
                $ownerId = User::factory()->owner()->create()->id;
                $workspace->owner_id = $ownerId;
            }

            $venue = Venue::query()->create([
                'owner_id' => $ownerId,
                'name' => $workspace->name ?: 'Venue',
                'slug' => Venue::uniqueSlugFrom($workspace->name ?: 'venue'),
                'description' => $workspace->description,
                'location_id' => $workspace->location_id,
                'address' => $workspace->location,
                'status' => ($workspace->status === WorkspaceStatus::Published || $workspace->status === 'published')
                    ? VenueStatus::Published
                    : VenueStatus::Draft,
                'featured' => (bool) $workspace->featured,
            ]);

            $mode = $workspace->booking_mode instanceof BookingMode
                ? $workspace->booking_mode
                : BookingMode::Whole;

            $workspace->venue_id = $venue->id;
            $workspace->type = $workspace->type ?: WorkspaceType::fromBookingMode($mode);
            $workspace->owner_id = $venue->owner_id;
        })->afterCreating(function (Workspace $workspace) {
            $workspace->loadMissing('venue');
            if (! $workspace->venue || $workspace->venue->hours()->exists()) {
                return;
            }

            $open = $workspace->opening_time ?: '08:00:00';
            $close = $workspace->closing_time ?: '22:00:00';
            for ($d = 0; $d <= 6; $d++) {
                $workspace->venue->hours()->create([
                    'weekday' => $d,
                    'opens_at' => $open,
                    'closes_at' => $close,
                    'is_closed' => false,
                ]);
            }
        });
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
