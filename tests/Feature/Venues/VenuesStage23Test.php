<?php

use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Models\Offer;
use App\Models\User;
use App\Models\Venue;
use App\Models\Workspace;

it('lets an owner create a venue then a unit and publish via checklist', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('owner.venues.store'), [
            'name' => 'Harbor Building',
            'slug' => 'harbor-building',
            'description' => 'Multi-unit venue',
            'status' => VenueStatus::Draft->value,
            'featured' => false,
        ])
        ->assertRedirect();

    $venue = Venue::query()->where('slug', 'harbor-building')->first();
    expect($venue)->not->toBeNull()
        ->and($venue->owner_id)->toBe($owner->id)
        ->and($venue->status)->toBe(VenueStatus::Draft);

    $this->actingAs($owner)
        ->post(route('owner.venues.units.store', $venue), [
            'name' => 'Room 1',
            'capacity' => 6,
            'booking_mode' => 'whole',
            'type' => WorkspaceType::MeetingRoom->value,
            'price_per_hour' => 40,
            'opening_time' => '09:00',
            'closing_time' => '18:00',
            'status' => WorkspaceStatus::Published->value,
        ])
        ->assertRedirect(route('owner.venues.show', $venue));

    expect($venue->units()->count())->toBe(1);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => 'Harbor Building',
            'slug' => 'harbor-building',
            'description' => 'Multi-unit venue',
            'status' => VenueStatus::Published->value,
            'featured' => true,
            'amenities' => [],
            'lat' => 31.5017,
            'lng' => 34.4668,
        ])
        ->assertRedirect(route('owner.venues.show', $venue));

    expect($venue->fresh()->status)->toBe(VenueStatus::Published);
});

it('blocks publishing a venue without a published unit', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Draft,
    ]);

    $this->actingAs($owner)
        ->put(route('owner.venues.update', $venue), [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => VenueStatus::Published->value,
            'featured' => false,
        ])
        ->assertSessionHasErrors('status');
});

it('forbids another owner from editing a venue', function () {
    $a = User::factory()->owner()->create();
    $b = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $a->id]);

    $this->actingAs($b)
        ->put(route('owner.venues.update', $venue), [
            'name' => 'Stolen',
            'slug' => $venue->slug,
            'status' => VenueStatus::Draft->value,
        ])
        ->assertForbidden();
});

it('lists published venues publicly and shows unit preselection', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
        'featured' => true,
        'slug' => 'public-venue-show',
    ]);
    Workspace::factory()->create([
        'owner_id' => $owner->id,
        'venue_id' => $venue->id,
        'status' => WorkspaceStatus::Published,
        'name' => 'Unit A',
        'price_per_hour' => 15,
    ]);
    $unitB = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'venue_id' => $venue->id,
        'status' => WorkspaceStatus::Published,
        'name' => 'Unit B',
        'price_per_hour' => 25,
        'type' => WorkspaceType::MeetingRoom,
    ]);

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Workspaces/Index')
            ->has('venues.data')
            ->has('unitTypes'));

    $this->get(route('spaces.show', $venue->slug).'?unit='.$unitB->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Venues/Show')
            ->where('selected_unit_id', $unitB->id)
            ->where('workspace.id', $unitB->id)
            ->has('units', 2));
});

it('lets an owner create a venue-scoped offer', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create(['owner_id' => $owner->id]);
    Workspace::factory()->create([
        'owner_id' => $owner->id,
        'venue_id' => $venue->id,
        'status' => WorkspaceStatus::Published,
    ]);

    $this->actingAs($owner)
        ->post(route('owner.offers.store'), [
            'scope' => 'venue',
            'venue_id' => $venue->id,
            'title' => 'Whole building 12%',
            'discount_percent' => 12,
            'is_active' => true,
        ])
        ->assertRedirect(route('owner.offers.index'));

    $offer = Offer::query()->where('venue_id', $venue->id)->whereNull('workspace_id')->first();
    expect($offer)->not->toBeNull()
        ->and($offer->discount_percent)->toBe(12)
        ->and($offer->owner_id)->toBe($owner->id);
});

it('lets admin create a venue for a chosen owner', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create();

    $this->actingAs($admin)
        ->post(route('admin.venues.store'), [
            'owner_id' => $owner->id,
            'name' => 'Admin Placed Venue',
            'slug' => 'admin-placed-venue',
            'status' => VenueStatus::Draft->value,
            'featured' => false,
        ])
        ->assertRedirect();

    $venue = Venue::query()->where('slug', 'admin-placed-venue')->first();
    expect($venue)->not->toBeNull()
        ->and($venue->owner_id)->toBe($owner->id);
});
