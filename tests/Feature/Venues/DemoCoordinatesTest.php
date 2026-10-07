<?php

use App\Models\Location;
use App\Support\DemoCityCoordinates;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;

it('location factory only uses allowed demo city coordinates', function () {
    $locations = Location::factory()->count(20)->create();

    foreach ($locations as $location) {
        expect($location->city)->toBeIn(DemoCityCoordinates::cities())
            ->and($location->lat)->not->toBeNull()
            ->and($location->lng)->not->toBeNull()
            ->and(DemoCityCoordinates::contains(
                $location->city,
                (float) $location->lat,
                (float) $location->lng,
            ))->toBeTrue("{$location->city} @ {$location->lat},{$location->lng} outside city box");
    }
});

it('never places factory locations at random world extremes', function () {
    $locations = Location::factory()->count(30)->create();

    foreach ($locations as $location) {
        $lat = abs((float) $location->lat);
        $lng = abs((float) $location->lng);
        // World faker often hits polar / ocean extremes; our cities stay mid-latitudes.
        expect($lat)->toBeGreaterThan(20)->toBeLessThan(35)
            ->and($lng)->toBeGreaterThan(30)->toBeLessThan(60);
    }
});

it('demo seeder locations match allowed cities with real coordinates', function () {
    // Fresh demo names from the seeder catalog.
    Artisan::call('db:seed', ['--class' => DemoSeeder::class]);

    $expected = [
        'Gaza Hub' => ['city' => 'Gaza', 'lat' => 31.5017, 'lng' => 34.4668],
        'Khan Younis Desk' => ['city' => 'Khan Younis', 'lat' => 31.3462, 'lng' => 34.3063],
        'Dubai Marina Desk' => ['city' => 'Dubai', 'lat' => 25.0805, 'lng' => 55.1403],
        'Riyadh Olaya Hub' => ['city' => 'Riyadh', 'lat' => 24.7136, 'lng' => 46.6753],
        'Kuwait Sharq Loft' => ['city' => 'Kuwait City', 'lat' => 29.3759, 'lng' => 47.9774],
    ];

    foreach ($expected as $name => $coords) {
        $loc = Location::query()->where('name', $name)->first();
        expect($loc)->not->toBeNull()
            ->and($loc->city)->toBe($coords['city'])
            ->and((float) $loc->lat)->toEqualWithDelta($coords['lat'], 0.0001)
            ->and((float) $loc->lng)->toEqualWithDelta($coords['lng'], 0.0001)
            ->and(DemoCityCoordinates::contains($coords['city'], (float) $loc->lat, (float) $loc->lng))->toBeTrue();
    }

    expect(Location::query()->whereIn('city', ['Ramallah', 'Nablus'])->whereIn('name', ['Ramallah Desk', 'Nablus Loft'])->count())->toBe(0);
});
