<?php

use App\Enums\VenueStatus;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\Workspace;
use App\Services\Venues\DemoVenueImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function seedDemoPoolOntoFakeDisk(): void
{
    // DemoVenueImageService reads from database/seeders/demo-images (real files).
    // Public disk is faked so attached copies land on the fake disk.
}

it('attaches deterministic demo images from the unit-type manifest', function () {
    seedDemoPoolOntoFakeDisk();

    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
        'slug' => 'manifest-venue',
    ]);
    Workspace::factory()->create([
        'venue_id' => $venue->id,
        'owner_id' => $owner->id,
        'type' => WorkspaceType::MeetingRoom,
    ]);

    $demo = app(DemoVenueImageService::class);
    $scenes = $demo->scenesForVenue($venue->fresh(['units']));
    expect($scenes[0])->toBeIn(['meeting_room', 'reception', 'lounge']);

    $result = $demo->attachForVenue($venue->fresh(['units', 'images']));
    expect($result['skipped'])->toBeFalse()
        ->and($result['attached'])->toBe(3);

    $images = $venue->fresh()->images()->orderBy('sort_order')->get();
    expect($images)->toHaveCount(3)
        ->and($images->every(fn (VenueImage $i) => $i->is_demo))->toBeTrue()
        ->and($images->first()->is_primary)->toBeTrue()
        ->and(Storage::disk('public')->exists($images->first()->path))->toBeTrue();
});

it('is idempotent and never touches real images', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
    ]);
    Workspace::factory()->create([
        'venue_id' => $venue->id,
        'owner_id' => $owner->id,
        'type' => WorkspaceType::HotDesk,
    ]);

    Storage::disk('public')->put('venues/real-keep.jpg', 'real-bytes');
    $real = $venue->images()->create([
        'path' => 'venues/real-keep.jpg',
        'is_primary' => true,
        'sort_order' => 0,
        'is_demo' => false,
    ]);

    $demo = app(DemoVenueImageService::class);
    $first = $demo->attachForVenue($venue->fresh(['units', 'images']));
    expect($first['attached'])->toBe(2);

    $real->refresh();
    expect($real->path)->toBe('venues/real-keep.jpg')
        ->and($real->is_primary)->toBeTrue()
        ->and($real->sort_order)->toBe(0)
        ->and($real->is_demo)->toBeFalse();

    $second = $demo->attachForVenue($venue->fresh(['units', 'images']));
    expect($second['skipped'])->toBeTrue()
        ->and($second['reason'])->toBe('enough')
        ->and($venue->fresh()->images()->count())->toBe(3)
        ->and($venue->fresh()->images()->where('is_demo', false)->count())->toBe(1);
});

it('dry-run lists actions without writing rows or files', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Draft,
        'slug' => 'dry-run-venue',
    ]);
    Workspace::factory()->create([
        'venue_id' => $venue->id,
        'owner_id' => $owner->id,
        'type' => WorkspaceType::PrivateOffice,
    ]);

    Artisan::call('demo:attach-images', [
        '--dry-run' => true,
        '--venue' => 'dry-run-venue',
    ]);

    expect($venue->fresh()->images()->count())->toBe(0)
        ->and(Artisan::output())->toContain('dry-run');
});

it('skips venues that already have three or more images', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
    ]);
    foreach (range(0, 2) as $i) {
        $path = "venues/full-{$i}.jpg";
        Storage::disk('public')->put($path, "x{$i}");
        $venue->images()->create([
            'path' => $path,
            'is_primary' => $i === 0,
            'sort_order' => $i,
            'is_demo' => false,
        ]);
    }

    $result = app(DemoVenueImageService::class)->attachForVenue($venue->fresh(['images']));
    expect($result['skipped'])->toBeTrue()
        ->and($result['reason'])->toBe('enough')
        ->and($venue->fresh()->images()->count())->toBe(3);
});

it('honors force-count when topping up beyond the default three', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
        'slug' => 'force-count-venue',
    ]);
    Workspace::factory()->create([
        'venue_id' => $venue->id,
        'owner_id' => $owner->id,
        'type' => WorkspaceType::TrainingRoom,
    ]);

    Artisan::call('demo:attach-images', [
        '--venue' => 'force-count-venue',
        '--force-count' => 5,
    ]);

    expect($venue->fresh()->images()->count())->toBe(5)
        ->and($venue->fresh()->images()->where('is_demo', true)->count())->toBe(5);
});

it('removes only demo images and deletes unreferenced files', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
        'slug' => 'remove-demo-venue',
    ]);

    Storage::disk('public')->put('venues/real.jpg', 'keep');
    Storage::disk('public')->put('venues/demo/shared.jpg', 'shared');
    Storage::disk('public')->put('venues/demo/only.jpg', 'only');

    $real = $venue->images()->create([
        'path' => 'venues/real.jpg',
        'is_primary' => true,
        'sort_order' => 0,
        'is_demo' => false,
    ]);
    $sharedDemo = $venue->images()->create([
        'path' => 'venues/demo/shared.jpg',
        'is_primary' => false,
        'sort_order' => 1,
        'is_demo' => true,
    ]);
    // Second venue still references the shared path — file must stay.
    $other = Venue::factory()->create(['owner_id' => $owner->id]);
    $other->images()->create([
        'path' => 'venues/demo/shared.jpg',
        'is_primary' => true,
        'sort_order' => 0,
        'is_demo' => false,
    ]);
    $onlyDemo = $venue->images()->create([
        'path' => 'venues/demo/only.jpg',
        'is_primary' => false,
        'sort_order' => 2,
        'is_demo' => true,
    ]);

    Artisan::call('demo:remove-images', ['--venue' => 'remove-demo-venue']);

    expect(VenueImage::query()->find($real->id))->not->toBeNull()
        ->and(VenueImage::query()->find($sharedDemo->id))->toBeNull()
        ->and(VenueImage::query()->find($onlyDemo->id))->toBeNull()
        ->and(Storage::disk('public')->exists('venues/real.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('venues/demo/shared.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('venues/demo/only.jpg'))->toBeFalse();
});

it('warns via security-check when demo images exist in production', function () {
    $venue = Venue::factory()->create(['status' => VenueStatus::Published]);
    Storage::disk('public')->put('venues/demo/x.jpg', 'x');
    $venue->images()->create([
        'path' => 'venues/demo/x.jpg',
        'is_primary' => true,
        'sort_order' => 0,
        'is_demo' => true,
    ]);

    config(['app.env' => 'production', 'app.debug' => false, 'session.secure' => true]);

    Artisan::call('app:security-check');
    expect(Artisan::output())->toContain('Demo venue gallery images');
});

it('shows demo images notice on the admin dashboard', function () {
    $admin = User::factory()->admin()->create();
    $venue = Venue::factory()->create(['status' => VenueStatus::Published]);
    Storage::disk('public')->put('venues/demo/y.jpg', 'y');
    $venue->images()->create([
        'path' => 'venues/demo/y.jpg',
        'is_primary' => true,
        'sort_order' => 0,
        'is_demo' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('demoImagesInUse', true)
        );
});

it('accepts multi-image venue gallery uploads and keeps primary stable when already set', function () {
    $owner = User::factory()->owner()->create();
    $venue = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
    ]);
    Storage::disk('public')->put('venues/existing.jpg', 'e');
    $existing = $venue->images()->create([
        'path' => 'venues/existing.jpg',
        'is_primary' => true,
        'sort_order' => 0,
        'is_demo' => false,
    ]);

    $this->actingAs($owner)
        ->post(route('owner.venues.images.store', $venue), [
            'images' => [
                UploadedFile::fake()->image('a.jpg', 40, 40),
                UploadedFile::fake()->image('b.jpg', 40, 40),
            ],
        ])
        ->assertRedirect();

    $venue->refresh();
    expect($venue->images()->count())->toBe(3)
        ->and($existing->fresh()->is_primary)->toBeTrue()
        ->and($venue->images()->where('is_demo', true)->count())->toBe(0);
});

it('renders venue cards and show pages with and without images', function () {
    $owner = User::factory()->owner()->create();
    $with = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
        'slug' => 'with-cover',
    ]);
    Workspace::factory()->create([
        'venue_id' => $with->id,
        'owner_id' => $owner->id,
        'status' => \App\Enums\WorkspaceStatus::Published,
    ]);
    Storage::disk('public')->put('venues/cover.jpg', 'cover');
    $with->images()->create([
        'path' => 'venues/cover.jpg',
        'is_primary' => true,
        'sort_order' => 0,
        'is_demo' => false,
    ]);

    $without = Venue::factory()->create([
        'owner_id' => $owner->id,
        'status' => VenueStatus::Published,
        'slug' => 'no-cover',
    ]);
    Workspace::factory()->create([
        'venue_id' => $without->id,
        'owner_id' => $owner->id,
        'status' => \App\Enums\WorkspaceStatus::Published,
    ]);

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('User/Workspaces/Index'));

    $this->get(route('spaces.show', $with))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Venues/Show')
            ->has('venue.images', 1)
        );

    $this->get(route('spaces.show', $without))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Venues/Show')
            ->has('venue.images', 0)
        );
});

it('varies scene rotation by venue id so neighbors differ', function () {
    $owner = User::factory()->owner()->create();
    $a = Venue::factory()->create(['owner_id' => $owner->id, 'status' => VenueStatus::Published]);
    $b = Venue::factory()->create(['owner_id' => $owner->id, 'status' => VenueStatus::Published]);
    foreach ([$a, $b] as $venue) {
        Workspace::factory()->create([
            'venue_id' => $venue->id,
            'owner_id' => $owner->id,
            'type' => WorkspaceType::HotDesk,
        ]);
    }

    // Force distinct ids with different modulo offsets when possible.
    $demo = app(DemoVenueImageService::class);
    $scenesA = $demo->scenesForVenue($a->fresh(['units']));
    $scenesB = $demo->scenesForVenue($b->fresh(['units']));

    if ($a->id % 3 !== $b->id % 3) {
        expect($scenesA)->not->toBe($scenesB);
    } else {
        expect($scenesA)->toBe($scenesB);
    }
});
