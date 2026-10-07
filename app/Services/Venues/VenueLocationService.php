<?php

namespace App\Services\Venues;

use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;
use App\Services\Audit\AuditLogger;
use App\Support\DemoCityCoordinates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Venue pin / address helpers: checklist, sync, and audit for published venues.
 */
class VenueLocationService
{
    public const FAR_FROM_CITY_KM = 100;

    public const AUDIT_ACTION = 'venue.location_updated';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function hasCoordinates(?Venue $venue): bool
    {
        if ($venue === null) {
            return false;
        }

        $venue->loadMissing('place');
        $lat = $venue->place?->lat;
        $lng = $venue->place?->lng;
        if ($lat === null || $lng === null) {
            return false;
        }

        // Null Island / empty form noise — not a real pin.
        if (abs((float) $lat) < 0.0001 && abs((float) $lng) < 0.0001) {
            return false;
        }

        return true;
    }

    public function applyWithoutCoordinates(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('location_id')
                ->orWhereDoesntHave('place')
                ->orWhereHas('place', function (Builder $p) {
                    $p->whereNull('lat')
                        ->orWhereNull('lng')
                        ->orWhere(function (Builder $z) {
                            $z->whereRaw('ABS(lat) < 0.0001')
                                ->whereRaw('ABS(lng) < 0.0001');
                        });
                });
        });
    }

    /**
     * @return array{
     *   has_unit: bool,
     *   has_published_unit: bool,
     *   has_coordinates: bool,
     *   can_publish: bool,
     *   is_published: bool,
     *   published_missing_location: bool
     * }
     */
    public function checklist(Venue $venue, int $publishedUnits): array
    {
        $hasCoords = $this->hasCoordinates($venue);
        $isPublished = $venue->status === VenueStatus::Published;

        return [
            'has_unit' => $venue->relationLoaded('units')
                ? $venue->units->isNotEmpty()
                : $venue->units()->exists(),
            'has_published_unit' => $publishedUnits > 0,
            'has_coordinates' => $hasCoords,
            'can_publish' => $publishedUnits > 0 && $hasCoords,
            'is_published' => $isPublished,
            'published_missing_location' => $isPublished && ! $hasCoords,
        ];
    }

    /**
     * Whether the incoming lat/lng (or existing place) count as a usable pin.
     */
    public function effectiveHasCoordinates(Venue $venue, mixed $lat, mixed $lng): bool
    {
        $latBlank = $lat === null || $lat === '';
        $lngBlank = $lng === null || $lng === '';

        if ($latBlank && $lngBlank) {
            return false;
        }

        if (! $latBlank && ! $lngBlank) {
            if (abs((float) $lat) < 0.0001 && abs((float) $lng) < 0.0001) {
                return false;
            }

            return true;
        }

        // One-sided — keep prior pin if present.
        return $this->hasCoordinates($venue);
    }

    /**
     * Block draft→published without a pin. Already-published venues keep status (warning only).
     *
     * @throws ValidationException
     */
    public function assertPublishAllowed(Venue $venue, mixed $requestedStatus, mixed $lat, mixed $lng, bool $wasPublished): void
    {
        $status = $requestedStatus instanceof VenueStatus
            ? $requestedStatus->value
            : (string) $requestedStatus;

        if ($status !== VenueStatus::Published->value) {
            return;
        }

        $hasPublishedUnit = $venue->units()->where('status', WorkspaceStatus::Published)->exists();
        if (! $hasPublishedUnit) {
            throw ValidationException::withMessages([
                'status' => __('venues.publish_requires_unit'),
            ]);
        }

        if (! $wasPublished && ! $this->effectiveHasCoordinates($venue, $lat, $lng)) {
            throw ValidationException::withMessages([
                'status' => __('venues.publish_requires_location'),
            ]);
        }
    }

    /**
     * @return array{lat: mixed, lng: mixed, address: mixed}
     */
    public function snapshot(Venue $venue): array
    {
        $venue->loadMissing('place');

        return [
            'lat' => $venue->place?->lat,
            'lng' => $venue->place?->lng,
            'address' => $venue->address,
        ];
    }

    public function syncCoordinates(Venue $venue, mixed $lat, mixed $lng): void
    {
        $venue->loadMissing('place');
        $latBlank = $lat === null || $lat === '';
        $lngBlank = $lng === null || $lng === '';

        if ($latBlank && $lngBlank) {
            if ($venue->place) {
                $venue->place->update(['lat' => null, 'lng' => null]);
            } elseif ($venue->location_id) {
                Location::query()->whereKey($venue->location_id)->update([
                    'lat' => null,
                    'lng' => null,
                ]);
            }

            return;
        }

        if ($latBlank || $lngBlank) {
            return;
        }

        if ($venue->place) {
            $venue->place->update([
                'lat' => $lat,
                'lng' => $lng,
            ]);

            return;
        }

        if ($venue->location_id) {
            Location::query()->whereKey($venue->location_id)->update([
                'lat' => $lat,
                'lng' => $lng,
            ]);

            return;
        }

        $place = Location::query()->create([
            'name' => $venue->name,
            'city' => null,
            'address' => $venue->address,
            'lat' => $lat,
            'lng' => $lng,
        ]);
        $venue->update(['location_id' => $place->id]);
    }

    /**
     * Audit coordinate/address changes on published venues only.
     *
     * @param  array{lat: mixed, lng: mixed, address: mixed}  $old
     * @param  array{lat: mixed, lng: mixed, address: mixed}  $new
     */
    public function auditIfChanged(Venue $venue, array $old, array $new, User $actor): void
    {
        if ($venue->status !== VenueStatus::Published) {
            return;
        }

        $normalize = static function (mixed $v): ?string {
            if ($v === null || $v === '') {
                return null;
            }

            return is_numeric($v) ? (string) round((float) $v, 7) : (string) $v;
        };

        $changed = $normalize($old['lat']) !== $normalize($new['lat'])
            || $normalize($old['lng']) !== $normalize($new['lng'])
            || (string) ($old['address'] ?? '') !== (string) ($new['address'] ?? '');

        if (! $changed) {
            return;
        }

        $this->audit->log(
            self::AUDIT_ACTION,
            $actor,
            $venue,
            $old,
            $new,
        );
    }

    /**
     * @return array<string, array{lat: float, lng: float}>
     */
    public function cityCenters(): array
    {
        $centers = [];
        foreach (DemoCityCoordinates::catalog() as $row) {
            $centers[$row['city']] = [
                'lat' => $row['lat'],
                'lng' => $row['lng'],
            ];
        }

        return $centers;
    }
}
