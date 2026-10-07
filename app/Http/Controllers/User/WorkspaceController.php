<?php

namespace App\Http\Controllers\User;

use App\Enums\BookingStatus;
use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Filters\VenueFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\NearMeSearchRequest;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Venue;
use App\Models\VenueSlugRedirect;
use App\Models\Workspace;
use App\Services\Availability\AvailabilityService;
use App\Services\Offers\ActiveOfferResolver;
use App\Services\Venues\NearMeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public catalog: venues (buildings). Workspace = bookable unit.
 */
class WorkspaceController extends Controller
{
    public function index(
        NearMeSearchRequest $request,
        VenueFilter $filter,
        ActiveOfferResolver $offers,
        AvailabilityService $availability,
        NearMeService $nearMe,
    ) {
        $query = Venue::query()
            ->with([
                'place:id,name,city,lat,lng',
                'amenities:id,name,slug',
                'images' => fn ($q) => $q->orderBy('sort_order')->limit(1),
                'hours',
                'availabilityExceptions',
                'units' => fn ($q) => $q
                    ->where('status', WorkspaceStatus::Published)
                    ->with(['activeOffers', 'hours', 'availabilityExceptions']),
            ]);

        $filter->apply($query);

        $origin = $request->origin();
        $excludedWithoutCoords = 0;
        $perPage = $filter->values()['per_page'];

        if ($origin !== null) {
            $excludedWithoutCoords = $nearMe->countMissingCoordinates(clone $query);
            $nearMe->applyBoundingBox($query, $origin['lat'], $origin['lng'], $origin['radius_km']);

            $candidates = $query->latest('venues.id')->limit(NearMeService::CANDIDATE_CAP)->get();
            if ($filter->wantsOpenNow()) {
                $candidates = $availability->filterVenuesOpenNow($candidates);
            }
            $ranked = $nearMe->rank($candidates, $origin['lat'], $origin['lng'], $origin['radius_km']);

            $page = max(1, (int) $request->integer('page', 1));
            $slice = $ranked->forPage($page, $perPage)->values();
            $venues = new LengthAwarePaginator(
                $slice,
                $ranked->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $venues->setCollection(
                $slice->map(fn (Venue $venue) => $this->venueCard($venue, $offers, $availability))
            );
        } elseif ($filter->wantsOpenNow()) {
            $all = $query->latest()->limit(200)->get();
            $open = $availability->filterVenuesOpenNow($all);
            $page = max(1, (int) $request->integer('page', 1));
            $slice = $open->forPage($page, $perPage)->values();
            $venues = new LengthAwarePaginator(
                $slice,
                $open->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $venues->setCollection(
                $slice->map(fn (Venue $venue) => $this->venueCard($venue, $offers, $availability))
            );
        } else {
            $venues = $query
                ->latest()
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (Venue $venue) => $this->venueCard($venue, $offers, $availability));
        }

        return Inertia::render('User/Workspaces/Index', [
            'venues' => $venues,
            'spaces' => $venues,
            'filters' => $filter->values(),
            'near_me' => [
                'active' => $origin !== null,
                'excluded_without_coords' => $excludedWithoutCoords,
                'origin' => $origin,
            ],
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'city']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'unitTypes' => \App\Enums\WorkspaceType::values(),
            'map' => [
                'tile_url' => config('map.tile_url'),
                'tile_attribution' => config('map.tile_attribution'),
                'default_center' => config('map.default_center'),
                'markers_url' => route('spaces.map'),
            ],
        ]);
    }

    public function show(Request $request, string $space): InertiaResponse|Response
    {
        if (ctype_digit($space)) {
            $unit = Workspace::query()->with(['venue.owner'])->find((int) $space);
            if ($unit?->venue) {
                abort_unless($this->venueIsPubliclyVisible($unit->venue), 404);

                return redirect()->to(
                    route('spaces.show', $unit->venue->slug).'?unit='.$unit->id,
                    301
                );
            }
        }

        $slugRedirect = VenueSlugRedirect::query()->where('slug', $space)->first();
        if ($slugRedirect?->venue) {
            $qs = $request->getQueryString();

            return redirect()->to(
                route('spaces.show', $slugRedirect->venue->slug).($qs ? '?'.$qs : ''),
                301
            );
        }

        $venue = Venue::query()->where('slug', $space)->first();
        if ($venue) {
            return $this->showVenue($request, $venue, app(AvailabilityService::class));
        }

        $unit = Workspace::query()->where('slug', $space)->with(['venue.owner'])->first();
        if ($unit?->venue) {
            abort_unless($this->venueIsPubliclyVisible($unit->venue), 404);

            return redirect()->to(
                route('spaces.show', $unit->venue->slug).'?unit='.$unit->id,
                301
            );
        }

        abort(404);
    }

    private function showVenue(Request $request, Venue $venue, AvailabilityService $availability): InertiaResponse|Response
    {
        $venue->loadMissing('owner:id,name,is_active');
        abort_unless($this->venueIsPubliclyVisible($venue), 404);

        $unitId = $request->query('unit') ? (int) $request->query('unit') : null;
        $units = $venue->units()
            ->with([
                'activeOffers',
                'amenities:id,name,slug,icon',
                'images' => fn ($q) => $q->orderBy('sort_order'),
                'hours',
                'availabilityExceptions',
            ])
            ->where('status', WorkspaceStatus::Published)
            ->orderBy('id')
            ->get();

        abort_if($units->isEmpty(), 404);

        $workspace = $unitId
            ? ($units->firstWhere('id', $unitId) ?: $units->first())
            : $units->first();

        $user = Auth::user();
        $pendingBookingId = null;
        if ($user) {
            $pendingBookingId = Booking::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('status', BookingStatus::Pending)
                ->value('id');
        }

        $venue->load([
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'amenities:id,name,slug,icon',
            'place:id,name,city,address,lat,lng',
            'hours',
            'availabilityExceptions',
        ]);

        $badge = $availability->openNowBadge($venue, $workspace);

        return Inertia::render('User/Venues/Show', [
            'venue' => $venue,
            'units' => $units,
            'workspace' => $workspace,
            'selected_unit_id' => $workspace->id,
            'can_book' => (bool) $user,
            'pending_booking_id' => $pendingBookingId,
            'availability' => [
                'badge' => $badge,
                'schedule' => $availability->weeklySchedule($workspace),
                'timezone' => $availability->timezoneFor($venue),
                'paused' => (bool) $availability->pauseReason($workspace),
            ],
            'map' => [
                'tile_url' => config('map.tile_url'),
                'tile_attribution' => config('map.tile_attribution'),
                'lat' => $venue->place?->lat !== null ? (float) $venue->place->lat : null,
                'lng' => $venue->place?->lng !== null ? (float) $venue->place->lng : null,
                'google_url' => ($venue->place?->lat !== null && $venue->place?->lng !== null)
                    ? 'https://www.google.com/maps?q='.$venue->place->lat.','.$venue->place->lng
                    : null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function venueCard(Venue $venue, ActiveOfferResolver $offers, AvailabilityService $availability): array
    {
        $units = $venue->units;
        $from = null;
        $types = [];
        foreach ($units as $unit) {
            $types[] = $unit->type?->value ?? (string) $unit->type;
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
            'featured' => (bool) $venue->featured,
            'cover_image_url' => $venue->cover_image_url,
            'place' => $venue->place,
            'amenities' => $venue->amenities,
            'unit_count' => $units->count(),
            'unit_types' => array_values(array_unique(array_filter($types))),
            'from_price' => $from,
            'open_badge' => $badge,
            'has_coordinates' => $venue->place?->lat !== null && $venue->place?->lng !== null,
            'distance_km' => $venue->getAttribute('distance_km'),
        ];
    }

    private function venueIsPubliclyVisible(Venue $venue): bool
    {
        $venue->loadMissing('owner:id,is_active');

        return $venue->status === VenueStatus::Published
            && $venue->owner
            && $venue->owner->is_active;
    }
}
