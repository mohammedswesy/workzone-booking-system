<?php

namespace App\Services\Venues;

use App\Models\Venue;
use App\Support\Geo\Haversine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Near-me query helpers. Visitor coordinates are never persisted.
 */
class NearMeService
{
    /** @var list<int|null> */
    public const RADIUS_OPTIONS_KM = [null, 5, 10, 25, 50, 100];

    public const CANDIDATE_CAP = 500;

    /**
     * @return array{lat: float, lng: float, radius_km: ?int}|null
     */
    public function parseOrigin(?float $lat, ?float $lng, mixed $radius): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        // Treat 0,0 as unset (Null Island / empty form noise).
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            return null;
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        $radiusKm = null;
        if ($radius !== null && $radius !== '' && $radius !== 'any') {
            $r = (int) $radius;
            if (in_array($r, [5, 10, 25, 50, 100], true)) {
                $radiusKm = $r;
            }
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'radius_km' => $radiusKm,
        ];
    }

    /**
     * Restrict to venues whose place has coordinates inside a bounding box.
     * When radius is null ("Any"), use a generous 500 km box for candidate capping.
     */
    public function applyBoundingBox(Builder $query, float $lat, float $lng, ?int $radiusKm): Builder
    {
        $boxKm = $radiusKm ?? 500;
        $deltas = Haversine::degreeDeltas($lat, (float) $boxKm);

        return $query->whereHas('place', function (Builder $place) use ($lat, $lng, $deltas) {
            $place->whereNotNull('lat')
                ->whereNotNull('lng')
                ->whereBetween('lat', [$lat - $deltas['lat'], $lat + $deltas['lat']])
                ->whereBetween('lng', [$lng - $deltas['lng'], $lng + $deltas['lng']]);
        });
    }

    /**
     * Attach distance_km, filter by radius, sort ascending. Caps candidates.
     *
     * @param  Collection<int, Venue>  $venues
     * @return Collection<int, Venue>
     */
    public function rank(Collection $venues, float $lat, float $lng, ?int $radiusKm): Collection
    {
        return $venues
            ->take(self::CANDIDATE_CAP)
            ->map(function (Venue $venue) use ($lat, $lng) {
                $pLat = $venue->place?->lat;
                $pLng = $venue->place?->lng;
                if ($pLat === null || $pLng === null) {
                    $venue->setAttribute('distance_km', null);

                    return $venue;
                }
                $km = Haversine::distanceKm($lat, $lng, (float) $pLat, (float) $pLng);
                $venue->setAttribute('distance_km', round($km, 2));

                return $venue;
            })
            ->filter(function (Venue $venue) use ($radiusKm) {
                $d = $venue->getAttribute('distance_km');
                if ($d === null) {
                    return false;
                }
                if ($radiusKm === null) {
                    return true;
                }

                return $d <= $radiusKm;
            })
            ->sortBy([
                fn (Venue $a, Venue $b) => ($a->distance_km <=> $b->distance_km)
                    ?: ($a->id <=> $b->id),
            ])
            ->values();
    }

    /**
     * Count published venues matching base filters that lack coordinates
     * (for the "excluded from near-me" note). Does not mutate $query.
     */
    public function countMissingCoordinates(Builder $baseQuery): int
    {
        return (clone $baseQuery)
            ->where(function (Builder $q) {
                $q->whereNull('location_id')
                    ->orWhereHas('place', fn (Builder $p) => $p->whereNull('lat')->orWhereNull('lng'))
                    ->orWhereDoesntHave('place');
            })
            ->count();
    }
}
