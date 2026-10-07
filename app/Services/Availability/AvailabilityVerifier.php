<?php

namespace App\Services\Availability;

use App\Models\AvailabilityException;
use App\Models\UnitHour;
use App\Models\Venue;
use App\Models\VenueHour;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

class AvailabilityVerifier
{
    /**
     * @return array{ok: bool, issue_count: int, checked_at: string, issues: list<array{code: string, message: string, venue_id?: int, workspace_id?: int}>}
     */
    public function verify(): array
    {
        $issues = [];

        $venues = Venue::query()->withCount('hours')->orderBy('id')->get(['id', 'name', 'slug', 'status', 'bookings_paused']);
        foreach ($venues as $venue) {
            if ((int) $venue->hours_count !== 7) {
                $issues[] = [
                    'code' => 'venue_missing_hours',
                    'message' => "Venue #{$venue->id} ({$venue->slug}) has {$venue->hours_count}/7 weekday hours rows.",
                    'venue_id' => $venue->id,
                ];
            } else {
                $days = VenueHour::query()->where('venue_id', $venue->id)->orderBy('weekday')->pluck('weekday')->all();
                if ($days !== [0, 1, 2, 3, 4, 5, 6]) {
                    $issues[] = [
                        'code' => 'venue_incomplete_weekdays',
                        'message' => "Venue #{$venue->id} ({$venue->slug}) is missing some weekday numbers.",
                        'venue_id' => $venue->id,
                    ];
                }
            }

            if ($venue->status?->value === 'published' && $venue->bookings_paused) {
                $issues[] = [
                    'code' => 'published_paused',
                    'message' => "Published venue #{$venue->id} ({$venue->slug}) has bookings paused.",
                    'venue_id' => $venue->id,
                ];
            }
        }

        $overrideUnits = Workspace::query()
            ->where('inherits_venue_hours', false)
            ->withCount('hours')
            ->orderBy('id')
            ->get(['id', 'name', 'venue_id', 'inherits_venue_hours']);

        foreach ($overrideUnits as $unit) {
            if ((int) $unit->hours_count !== 7) {
                $issues[] = [
                    'code' => 'unit_override_missing_hours',
                    'message' => "Unit #{$unit->id} overrides venue hours but has {$unit->hours_count}/7 unit_hours rows.",
                    'workspace_id' => $unit->id,
                    'venue_id' => $unit->venue_id,
                ];
            } else {
                $days = UnitHour::query()->where('workspace_id', $unit->id)->orderBy('weekday')->pluck('weekday')->all();
                if ($days !== [0, 1, 2, 3, 4, 5, 6]) {
                    $issues[] = [
                        'code' => 'unit_override_incomplete_weekdays',
                        'message' => "Unit #{$unit->id} override schedule is missing some weekdays.",
                        'workspace_id' => $unit->id,
                        'venue_id' => $unit->venue_id,
                    ];
                }
            }
        }

        $badExceptions = AvailabilityException::query()
            ->whereColumn('ends_on', '<', 'starts_on')
            ->orderBy('id')
            ->get(['id', 'venue_id', 'workspace_id', 'starts_on', 'ends_on']);

        foreach ($badExceptions as $ex) {
            $issues[] = [
                'code' => 'exception_end_before_start',
                'message' => "Exception #{$ex->id} ends before it starts ({$ex->starts_on} → {$ex->ends_on}).",
                'venue_id' => $ex->venue_id,
                'workspace_id' => $ex->workspace_id,
            ];
        }

        $payload = [
            'ok' => $issues === [],
            'issue_count' => count($issues),
            'checked_at' => now()->toIso8601String(),
            'issues' => $issues,
        ];

        Cache::put('availability.verify.last', $payload, now()->addDays(7));

        return $payload;
    }
}
