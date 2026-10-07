<?php

use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;
use App\Models\Workspace;
use App\Policies\VenuePolicy;
use App\Services\Venues\VenueLocationService;

function locationVenue(User $owner, array $placeAttrs = [], array $venueAttrs = []): Venue
{
    $location = Location::factory()->create(array_merge([
        'lat' => null,
        'lng' => null,
    ], $placeAttrs));

    $venue = Venue::factory()->create(array_merge([
        'owner_id' => $owner->id,
        'location_id' => $location->id,
        'status' => VenueStatus::Draft,
        'address' => 'Test St',
    ], $venueAttrs));

    Workspace::factory()->create([
        'owner_id' => $owner->id,
        'venue_id' => $venue->id,
        'status' => WorkspaceStatus::Published,
    ]);

    return $venue->fresh(['place', 'units']);
}

it('allows an owner to update their own venue location and forbids another owner', function () {
    $ownerA = User::factory()->owner()->create();
    $ownerB = User::factory()->owner()->create();
    $venue = locationVenue($ownerA, ['lat' => 31.5, 'lng' => 34.47]);

    $policy = app(VenuePolicy::class);
    expect($policy->update($ownerA, $venue))->toBeTrue()
        ->and($policy->update($ownerB, $venue))->toBeFalse();

    $this->actingAs($ownerA)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'address' => 'Omar St',
            'lat' => 31.5017,
            'lng' => 34.4668,
        ])
        ->assertRedirect();

    expect((float) $venue->fresh()->place->lat)->toEqualWithDelta(31.5017, 0.0001);

    $this->actingAs($ownerB)
        ->put(route('owner.venues.update', $venue), [
            'name' => 'Hijack',
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
            'lat' => 25.0,
            'lng' => 55.0,
        ])
        ->assertForbidden();
});

it('requires coordinates on the publish checklist and blocks draft publish without a pin', function () {
    $owner = User::factory()->owner()->create();
    $venue = locationVenue($owner);

    $checklist = app(VenueLocationService::class)->checklist($venue, 1);
    expect($checklist['has_coordinates'])->toBeFalse()
        ->and($checklist['can_publish'])->toBeFalse();

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Published->value,
            'lat' => null,
            'lng' => null,
        ])
        ->assertSessionHasErrors('status');

    expect($venue->fresh()->status)->toBe(VenueStatus::Draft);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Published->value,
            'lat' => 31.5017,
            'lng' => 34.4668,
        ])
        ->assertRedirect();

    expect($venue->fresh()->status)->toBe(VenueStatus::Published)
        ->and(app(VenueLocationService::class)->hasCoordinates($venue->fresh()))->toBeTrue();
});

it('keeps already published venues without coordinates published but flags a warning', function () {
    $owner = User::factory()->owner()->create();
    $venue = locationVenue($owner, ['lat' => null, 'lng' => null], [
        'status' => VenueStatus::Published,
    ]);

    $checklist = app(VenueLocationService::class)->checklist($venue->fresh(['place', 'units']), 1);
    expect($checklist['is_published'])->toBeTrue()
        ->and($checklist['published_missing_location'])->toBeTrue()
        ->and($checklist['can_publish'])->toBeFalse();

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Published->value,
            'address' => 'Still published',
            'lat' => null,
            'lng' => null,
        ])
        ->assertRedirect();

    expect($venue->fresh()->status)->toBe(VenueStatus::Published);
});

it('writes an audit log when a published venue location changes', function () {
    $owner = User::factory()->owner()->create();
    $venue = locationVenue($owner, ['lat' => 31.5, 'lng' => 34.47], [
        'status' => VenueStatus::Published,
        'address' => 'Old address',
    ]);

    $before = AuditLog::query()->count();

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Published->value,
            'address' => 'New address',
            'lat' => 31.5017,
            'lng' => 34.4668,
        ])
        ->assertRedirect();

    expect(AuditLog::query()->count())->toBe($before + 1);

    $log = AuditLog::query()->latest('id')->first();
    expect($log->action)->toBe(VenueLocationService::AUDIT_ACTION)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->subject_id)->toBe($venue->id)
        ->and($log->old_values['address'])->toBe('Old address')
        ->and($log->new_values['address'])->toBe('New address');
});

it('exposes admin without-coordinates filter counter and dashboard stat', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create();
    locationVenue($owner, ['lat' => null, 'lng' => null]);
    locationVenue($owner, ['lat' => 31.5, 'lng' => 34.47]);

    $this->actingAs($admin)
        ->get(route('admin.venues.index', ['without_coordinates' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('without_coordinates_count', fn ($n) => $n >= 1)
            ->where('filters.without_coordinates', true)
            ->has('venues.data', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.venues_without_coordinates', fn ($n) => $n >= 1)
        );
});

it('shows owner missing-location banner data on dashboard and venue list', function () {
    $owner = User::factory()->owner()->create();
    locationVenue($owner, ['lat' => null, 'lng' => null]);

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('missingLocationVenues', 1)
            ->where('stats.venues_without_coordinates', 1)
        );

    $this->actingAs($owner)
        ->get(route('owner.venues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('missing_coordinates_count', 1)
            ->where('venues.data.0.has_coordinates', false)
        );
});
