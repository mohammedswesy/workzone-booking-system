<?php

namespace App\Http\Controllers\User;

use App\Enums\WorkspaceStatus;
use App\Filters\VenueFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\NearMeSearchRequest;
use App\Models\Venue;
use App\Services\Availability\AvailabilityService;
use App\Services\Offers\ActiveOfferResolver;
use App\Services\Venues\NearMeService;

/**
 * Lightweight public map markers for the current /spaces filters.
 */
class VenueMapController extends Controller
{
    public function __invoke(
        NearMeSearchRequest $request,
        VenueFilter $filter,
        AvailabilityService $availability,
        ActiveOfferResolver $offers,
        NearMeService $nearMe,
    ) {
        $limit = max(1, min(200, (int) config('map.markers_limit', 100)));

        $query = Venue::query()
            ->with([
                'place:id,name,city,lat,lng,address',
                'images' => fn ($q) => $q->orderBy('sort_order')->limit(1),
                'units' => fn ($q) => $q
                    ->where('status', WorkspaceStatus::Published)
                    ->with(['activeOffers', 'hours', 'availabilityExceptions']),
                'hours',
                'availabilityExceptions',
            ]);

        $filter->apply($query);

        $origin = $request->origin();
        $excludedWithoutCoords = 0;

        if ($origin !== null) {
            $excludedWithoutCoords = $nearMe->countMissingCoordinates(clone $query);
            $nearMe->applyBoundingBox($query, $origin['lat'], $origin['lng'], $origin['radius_km']);
            $venues = $query->latest('venues.id')->limit(NearMeService::CANDIDATE_CAP)->get();
            if ($filter->wantsOpenNow()) {
                $venues = $availability->filterVenuesOpenNow($venues);
            }
            $venues = $nearMe->rank($venues, $origin['lat'], $origin['lng'], $origin['radius_km'])
                ->take($limit);
        } else {
            $venues = $query->latest()->limit($limit * 3)->get();
            if ($filter->wantsOpenNow()) {
                $venues = $availability->filterVenuesOpenNow($venues);
            }
            $venues = $venues
                ->filter(fn (Venue $v) => $v->place?->lat !== null && $v->place?->lng !== null)
                ->take($limit);
        }

        $markers = $venues
            ->map(function (Venue $venue) use ($offers, $availability) {
                $units = $venue->units;
                $from = null;
                foreach ($units as $unit) {
                    $offer = $offers->for($unit);
                    $percent = (int) ($offer?->discount_percent ?? 0);
                    $effective = $percent > 0
                        ? round((float) $unit->price_per_hour * (1 - $percent / 100), 2)
                        : (float) $unit->price_per_hour;
                    $from = $from === null ? $effective : min($from, $effective);
                }

                $badge = $availability->openNowBadge($venue, $units->first());

                return [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'slug' => $venue->slug,
                    'lat' => (float) $venue->place->lat,
                    'lng' => (float) $venue->place->lng,
                    'cover_image_url' => $venue->cover_image_url,
                    'from_price' => $from,
                    'unit_count' => $units->count(),
                    'open_status' => $badge['status'],
                    'distance_km' => $venue->getAttribute('distance_km'),
                    'url' => route('spaces.show', $venue->slug),
                ];
            })
            ->values();

        return response()->json([
            'markers' => $markers,
            'meta' => [
                'count' => $markers->count(),
                'limit' => $limit,
                'filters' => $filter->values(),
                'near_me' => [
                    'active' => $origin !== null,
                    'excluded_without_coords' => $excludedWithoutCoords,
                    'origin' => $origin,
                ],
            ],
        ]);
    }
}
