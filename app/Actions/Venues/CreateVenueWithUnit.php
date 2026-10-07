<?php

namespace App\Actions\Venues;

use App\Enums\BookingMode;
use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Models\Venue;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Legacy helper for old workspace store routes: create a venue shell + first unit.
 * Prefer Owner/Admin VenueController unit forms for new UI flows.
 */
class CreateVenueWithUnit
{
    /**
     * @param  array<string, mixed>  $workspaceAttributes
     */
    public function handle(array $workspaceAttributes): Workspace
    {
        return DB::transaction(function () use ($workspaceAttributes) {
            $ownerId = (int) $workspaceAttributes['owner_id'];
            $name = (string) ($workspaceAttributes['name'] ?? 'Workspace');
            $status = $workspaceAttributes['status'] ?? WorkspaceStatus::Published->value;
            $statusValue = $status instanceof WorkspaceStatus ? $status->value : (string) $status;

            $venue = Venue::query()->create([
                'owner_id' => $ownerId,
                'name' => $name,
                'slug' => Venue::uniqueSlugFrom($name),
                'description' => $workspaceAttributes['description'] ?? null,
                'location_id' => $workspaceAttributes['location_id'] ?? null,
                'address' => $workspaceAttributes['location'] ?? null,
                'status' => in_array($statusValue, VenueStatus::values(), true)
                    ? $statusValue
                    : VenueStatus::Draft->value,
                'featured' => (bool) ($workspaceAttributes['featured'] ?? false),
            ]);

            $mode = $workspaceAttributes['booking_mode'] ?? BookingMode::Seat->value;
            $modeEnum = $mode instanceof BookingMode
                ? $mode
                : (BookingMode::tryFrom((string) $mode) ?? BookingMode::Seat);

            $workspaceAttributes['venue_id'] = $venue->id;
            $workspaceAttributes['owner_id'] = $venue->owner_id;
            $workspaceAttributes['type'] = $workspaceAttributes['type']
                ?? WorkspaceType::fromBookingMode($modeEnum)->value;
            $workspaceAttributes['booking_mode'] = $modeEnum->value;

            $workspace = Workspace::query()->create($workspaceAttributes);

            for ($d = 0; $d <= 6; $d++) {
                $venue->hours()->firstOrCreate(
                    ['weekday' => $d],
                    [
                        'opens_at' => $workspaceAttributes['opening_time'] ?? '08:00:00',
                        'closes_at' => $workspaceAttributes['closing_time'] ?? '22:00:00',
                        'is_closed' => false,
                    ]
                );
            }

            return $workspace;
        });
    }
}
