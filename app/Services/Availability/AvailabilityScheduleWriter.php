<?php

namespace App\Services\Availability;

use App\Enums\AvailabilityExceptionType;
use App\Enums\AvailabilityScope;
use App\Models\AvailabilityException;
use App\Models\UnitHour;
use App\Models\Venue;
use App\Models\VenueHour;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityScheduleWriter
{
    /**
     * @param  list<array{weekday: int, opens_at?: ?string, closes_at?: ?string, is_closed?: bool}>  $days
     */
    public function syncVenueHours(Venue $venue, array $days): void
    {
        DB::transaction(function () use ($venue, $days) {
            foreach ($days as $day) {
                $weekday = (int) $day['weekday'];
                VenueHour::query()->updateOrCreate(
                    ['venue_id' => $venue->id, 'weekday' => $weekday],
                    [
                        'is_closed' => (bool) ($day['is_closed'] ?? false),
                        'opens_at' => ($day['is_closed'] ?? false) ? null : $this->normalize($day['opens_at'] ?? null),
                        'closes_at' => ($day['is_closed'] ?? false) ? null : $this->normalize($day['closes_at'] ?? null),
                    ]
                );
            }
        });
    }

    /**
     * @param  list<array{weekday: int, opens_at?: ?string, closes_at?: ?string, is_closed?: bool}>  $days
     */
    public function syncUnitHours(Workspace $unit, array $days, bool $inheritsVenueHours): void
    {
        DB::transaction(function () use ($unit, $days, $inheritsVenueHours) {
            $unit->update(['inherits_venue_hours' => $inheritsVenueHours]);

            if ($inheritsVenueHours) {
                UnitHour::query()->where('workspace_id', $unit->id)->delete();

                return;
            }

            foreach ($days as $day) {
                $weekday = (int) $day['weekday'];
                UnitHour::query()->updateOrCreate(
                    ['workspace_id' => $unit->id, 'weekday' => $weekday],
                    [
                        'is_closed' => (bool) ($day['is_closed'] ?? false),
                        'opens_at' => ($day['is_closed'] ?? false) ? null : $this->normalize($day['opens_at'] ?? null),
                        'closes_at' => ($day['is_closed'] ?? false) ? null : $this->normalize($day['closes_at'] ?? null),
                    ]
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertException(array $data): AvailabilityException
    {
        $scope = AvailabilityScope::from($data['scope']);
        if ($scope === AvailabilityScope::Venue && empty($data['venue_id'])) {
            throw ValidationException::withMessages(['venue_id' => 'Venue is required.']);
        }
        if ($scope === AvailabilityScope::Unit && empty($data['workspace_id'])) {
            throw ValidationException::withMessages(['workspace_id' => 'Unit is required.']);
        }

        $type = AvailabilityExceptionType::from($data['type']);
        $payload = [
            'scope' => $scope->value,
            'venue_id' => $scope === AvailabilityScope::Venue ? $data['venue_id'] : ($data['venue_id'] ?? null),
            'workspace_id' => $scope === AvailabilityScope::Unit ? $data['workspace_id'] : null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'type' => $type->value,
            'reason' => $data['reason'] ?? null,
            'opens_at' => $type === AvailabilityExceptionType::SpecialHours
                ? $this->normalize($data['opens_at'] ?? null)
                : null,
            'closes_at' => $type === AvailabilityExceptionType::SpecialHours
                ? $this->normalize($data['closes_at'] ?? null)
                : null,
        ];

        if (! empty($data['id'])) {
            $exception = AvailabilityException::query()->findOrFail($data['id']);
            $exception->update($payload);

            return $exception->fresh();
        }

        return AvailabilityException::query()->create($payload);
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
