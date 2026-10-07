<?php

use App\Enums\VenueStatus;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;

it('rejects latitude outside -90..90', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $owner->id, 'status' => VenueStatus::Draft]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'lat' => 120,
            'lng' => 34.4,
        ])
        ->assertSessionHasErrors('lat');
});

it('rejects longitude outside -180..180', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $owner->id, 'status' => VenueStatus::Draft]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'lat' => 31.5,
            'lng' => 200,
        ])
        ->assertSessionHasErrors('lng');
});

it('requires both coordinates together', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $owner->id, 'status' => VenueStatus::Draft]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'lat' => 31.5017,
            'lng' => null,
        ])
        ->assertSessionHasErrors('lng');

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'lat' => null,
            'lng' => 34.4668,
        ])
        ->assertSessionHasErrors('lat');
});

it('rejects more than 7 decimal places', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $owner->id, 'status' => VenueStatus::Draft]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'lat' => '31.50171234',
            'lng' => '34.46680000',
        ])
        ->assertSessionHasErrors('lat');
});

it('accepts valid coordinates and persists them on the linked location', function () {
    $owner = User::factory()->owner()->create();
    $location = Location::factory()->create(['lat' => null, 'lng' => null]);
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'location_id' => $location->id,
        'status' => VenueStatus::Draft,
    ]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'location_id' => $location->id,
            'lat' => '31.5017',
            'lng' => '34.4668',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $location->refresh();
    expect((float) $location->lat)->toEqualWithDelta(31.5017, 0.0000001)
        ->and((float) $location->lng)->toEqualWithDelta(34.4668, 0.0000001);
});

it('allows clearing both coordinates', function () {
    $owner = User::factory()->owner()->create();
    $location = Location::factory()->create(['lat' => 31.5, 'lng' => 34.4]);
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'location_id' => $location->id,
        'status' => VenueStatus::Draft,
    ]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'location_id' => $location->id,
            'lat' => null,
            'lng' => null,
        ])
        ->assertRedirect();

    $location->refresh();
    expect($location->lat)->toBeNull()
        ->and($location->lng)->toBeNull();
});

it('hints when latitude looks like a swapped longitude', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $owner->id, 'status' => VenueStatus::Draft]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'lat' => 120,
            'lng' => 31.5,
        ])
        ->assertSessionHasErrors('lat');

    $errors = session('errors')->get('lat');
    expect(collect($errors)->implode(' '))->toContain(__('venues.validation.coords_swapped'));
});
