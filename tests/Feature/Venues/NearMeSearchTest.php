<?php

use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;
use App\Models\Workspace;
use App\Support\Geo\Haversine;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

function nearMeVenue(string $name, float $lat, float $lng, ?User $owner = null): Venue
{
    $owner ??= User::factory()->owner()->create(['is_active' => true]);
    $location = Location::factory()->create([
        'name' => $name.' Place',
        'city' => explode(' ', $name)[0],
        'lat' => $lat,
        'lng' => $lng,
    ]);
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'location_id' => $location->id,
        'status' => VenueStatus::Published,
    ]);
    Workspace::factory()->create([
        'owner_id' => $owner->id,
        'venue_id' => $venue->id,
        'status' => WorkspaceStatus::Published,
        'price_per_hour' => 20,
    ]);

    return $venue->fresh(['place', 'units']);
}

function nearMeVenueWithoutCoords(string $name, ?User $owner = null): Venue
{
    $owner ??= User::factory()->owner()->create(['is_active' => true]);
    $location = Location::factory()->create([
        'name' => $name.' Place',
        'city' => $name,
        'lat' => null,
        'lng' => null,
    ]);
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'location_id' => $location->id,
        'status' => VenueStatus::Published,
    ]);
    Workspace::factory()->create([
        'owner_id' => $owner->id,
        'venue_id' => $venue->id,
        'status' => WorkspaceStatus::Published,
    ]);

    return $venue;
}

it('computes haversine distances accurately for known cities', function () {
    // Gaza City → Khan Younis ≈ 22–28 km
    $gazaToKhan = Haversine::distanceKm(31.5017, 34.4668, 31.3462, 34.3063);
    expect($gazaToKhan)->toBeGreaterThan(15)->toBeLessThan(35);

    // Gaza → Dubai is thousands of km
    $gazaToDubai = Haversine::distanceKm(31.5017, 34.4668, 25.0805, 55.1403);
    expect($gazaToDubai)->toBeGreaterThan(1500);

    // Same point ≈ 0
    expect(Haversine::distanceKm(24.7136, 46.6753, 24.7136, 46.6753))->toEqualWithDelta(0, 0.01);

    // Kuwait City → Riyadh is hundreds of km
    $kwToRiyadh = Haversine::distanceKm(29.3759, 47.9774, 24.7136, 46.6753);
    expect($kwToRiyadh)->toBeGreaterThan(400)->toBeLessThan(900);
});

it('orders near-me results by ascending distance with real city coordinates', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    nearMeVenue('Gaza City Hub', 31.5017, 34.4668, $owner);
    nearMeVenue('Khan Younis Hub', 31.3462, 34.3063, $owner);
    nearMeVenue('Dubai Marina', 25.0805, 55.1403, $owner);
    nearMeVenue('Riyadh Olaya', 24.7136, 46.6753, $owner);
    nearMeVenue('Kuwait Sharq', 29.3759, 47.9774, $owner);

    $this->get(route('spaces.index', [
        'near_lat' => 31.510,
        'near_lng' => 34.470,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Workspaces/Index')
            ->where('near_me.active', true)
            ->where('venues.data.0.name', 'Gaza City Hub')
            ->where('venues.data.1.name', 'Khan Younis Hub')
            ->has('venues.data.0.distance_km')
        );
});

it('filters by radius in kilometers', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    nearMeVenue('Near Gaza', 31.5017, 34.4668, $owner);
    nearMeVenue('Far Dubai', 25.0805, 55.1403, $owner);

    $this->get(route('spaces.index', [
        'near_lat' => 31.5017,
        'near_lng' => 34.4668,
        'radius_km' => 50,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('venues.data.0.name', 'Near Gaza')
            ->has('venues.data', 1)
        );
});

it('combines near-me with city filter', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    $gaza = nearMeVenue('Gaza Spot', 31.5017, 34.4668, $owner);
    $gaza->place->update(['city' => 'Gaza']);
    $dubai = nearMeVenue('Dubai Spot', 25.0805, 55.1403, $owner);
    $dubai->place->update(['city' => 'Dubai']);

    $this->get(route('spaces.index', [
        'near_lat' => 31.5,
        'near_lng' => 34.47,
        'city' => 'Gaza',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('venues.data', 1)
            ->where('venues.data.0.name', 'Gaza Spot')
        );
});

it('excludes venues without coordinates from near-me but keeps them in normal search', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    nearMeVenue('With Coords', 31.5017, 34.4668, $owner);
    nearMeVenueWithoutCoords('No Coords', $owner);

    $this->get(route('spaces.index', [
        'near_lat' => 31.5017,
        'near_lng' => 34.4668,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('venues.data.0.name', 'With Coords')
            ->has('venues.data', 1)
            ->where('near_me.excluded_without_coords', fn ($n) => $n >= 1)
        );

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('venues.data', fn ($data) => collect($data)->contains(fn ($row) => ($row['name'] ?? null) === 'No Coords'))
        );
});

it('validates near-me ranges, one-sided input, and rejects 0,0', function () {
    $this->get(route('spaces.index', ['near_lat' => 120, 'near_lng' => 34]))
        ->assertRedirect(route('spaces.index'));

    $this->getJson(route('spaces.map', ['near_lat' => 31.5, 'near_lng' => 200]))
        ->assertStatus(422);

    $this->getJson(route('spaces.map', ['near_lat' => 31.5]))
        ->assertStatus(422);

    $this->getJson(route('spaces.map', ['near_lng' => 34.4]))
        ->assertStatus(422);

    $this->getJson(route('spaces.map', ['near_lat' => 0, 'near_lng' => 0]))
        ->assertStatus(422);

    $this->getJson(route('spaces.map', ['near_lat' => 31.5, 'near_lng' => 34.4, 'radius_km' => 7]))
        ->assertStatus(422);
});

it('keeps distance pagination stable across pages', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    for ($i = 0; $i < 5; $i++) {
        nearMeVenue("Gaza Spot {$i}", 31.5017 + ($i * 0.01), 34.4668, $owner);
    }

    $page1Ids = [];
    $page1Max = null;
    $this->get(route('spaces.index', [
        'near_lat' => 31.5017,
        'near_lng' => 34.4668,
        'per_page' => 2,
        'page' => 1,
    ]))
        ->assertOk()
        ->assertInertia(function ($page) use (&$page1Ids, &$page1Max) {
            $data = $page->toArray()['props']['venues']['data'];
            $page1Ids = collect($data)->pluck('id')->all();
            $page1Max = collect($data)->max('distance_km');
            expect($data)->toHaveCount(2);

            return $page;
        });

    $this->get(route('spaces.index', [
        'near_lat' => 31.5017,
        'near_lng' => 34.4668,
        'per_page' => 2,
        'page' => 2,
    ]))
        ->assertOk()
        ->assertInertia(function ($page) use ($page1Ids, $page1Max) {
            $data = $page->toArray()['props']['venues']['data'];
            $ids2 = collect($data)->pluck('id')->all();
            expect(array_intersect($page1Ids, $ids2))->toBeEmpty()
                ->and(collect($data)->min('distance_km'))->toBeGreaterThanOrEqual($page1Max);

            return $page;
        });
});

it('returns distance_km on map.json public fields and applies catalog rate limit', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    nearMeVenue('Map Gaza', 31.5017, 34.4668, $owner);

    $json = $this->getJson(route('spaces.map', [
        'near_lat' => 31.5017,
        'near_lng' => 34.4668,
    ]))->assertOk()->json();

    expect($json['markers'])->not->toBeEmpty()
        ->and($json['markers'][0])->toHaveKeys(['id', 'name', 'lat', 'lng', 'distance_km', 'url', 'from_price'])
        ->and($json['markers'][0])->not->toHaveKey('owner_id')
        ->and($json['meta']['near_me']['active'])->toBeTrue();

    $indexMiddleware = collect(app('router')->getRoutes()->getByName('spaces.index')->gatherMiddleware());
    $mapMiddleware = collect(app('router')->getRoutes()->getByName('spaces.map')->gatherMiddleware());
    expect($indexMiddleware->contains('throttle:spaces-catalog'))->toBeTrue()
        ->and($mapMiddleware->contains('throttle:spaces-catalog'))->toBeTrue();

    RateLimiter::for('spaces-catalog', fn () => Limit::perMinute(1)->by('near-me-rl-test'));
    RateLimiter::clear('near-me-rl-test');

    $this->get(route('spaces.index'))->assertOk();
    $this->get(route('spaces.index'))->assertStatus(429);

    // Restore default limiter for later tests in the same process.
    RateLimiter::for('spaces-catalog', fn ($request) => Limit::perMinute(60)->by($request->ip().'|spaces-catalog'));
});

it('does not persist visitor near-me coordinates', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    nearMeVenue('Persist Check', 31.5017, 34.4668, $owner);

    $beforeLocations = Location::query()->count();
    $beforeUsers = User::query()->count();

    $this->get(route('spaces.index', [
        'near_lat' => 31.5123456,
        'near_lng' => 34.4712345,
        'radius_km' => 25,
    ]))->assertOk();

    $this->getJson(route('spaces.map', [
        'near_lat' => 31.5123456,
        'near_lng' => 34.4712345,
    ]))->assertOk();

    expect(Location::query()->count())->toBe($beforeLocations)
        ->and(User::query()->count())->toBe($beforeUsers);

    expect(session()->all())->not->toHaveKey('near_lat')
        ->and(session()->all())->not->toHaveKey('near_lng');

    expect(
        Location::query()
            ->where('lat', 31.5123456)
            ->orWhere('lng', 34.4712345)
            ->count()
    )->toBe(0);

    if (Schema::hasTable('audit_logs')) {
        expect(DB::table('audit_logs')->where('payload', 'like', '%31.5123456%')->count())->toBe(0);
    }
});
