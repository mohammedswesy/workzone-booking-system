<?php

namespace App\Services\Availability;

use App\Models\Venue;
use App\Models\VenueHour;
use App\Models\Workspace;

/**
 * Ensures every venue has a full 7-day weekly schedule.
 */
class VenueHoursEnsuring
{
    public function ensure(Venue $venue, ?string $opensAt = '08:00:00', ?string $closesAt = '22:00:00'): void
    {
        $open = $this->normalize($opensAt) ?? '08:00:00';
        $close = $this->normalize($closesAt) ?? '22:00:00';

        for ($d = 0; $d <= 6; $d++) {
            VenueHour::query()->firstOrCreate(
                ['venue_id' => $venue->id, 'weekday' => $d],
                [
                    'opens_at' => $open,
                    'closes_at' => $close,
                    'is_closed' => false,
                ]
            );
        }
    }

    public function ensureFromUnit(Venue $venue, Workspace $unit): void
    {
        $this->ensure(
            $venue,
            is_string($unit->opening_time) ? $unit->opening_time : '08:00:00',
            is_string($unit->closing_time) ? $unit->closing_time : '22:00:00',
        );
    }

    public function isComplete(Venue $venue): bool
    {
        $weekdays = $venue->relationLoaded('hours')
            ? $venue->hours->pluck('weekday')->map(fn ($d) => (int) $d)->unique()->sort()->values()->all()
            : VenueHour::query()->where('venue_id', $venue->id)->orderBy('weekday')->pluck('weekday')->map(fn ($d) => (int) $d)->all();

        return $weekdays === [0, 1, 2, 3, 4, 5, 6];
    }

    private function normalize(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time.':00';
        }

        return $time;
    }
}
