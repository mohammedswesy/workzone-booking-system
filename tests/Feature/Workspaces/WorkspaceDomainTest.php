<?php

use App\Enums\WorkspaceStatus;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceImage;
use App\Services\Workspaces\WorkspaceGalleryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('generates a unique slug and links a place location', function () {
    $workspace = Workspace::factory()->create([
        'name' => 'Legacy Space',
        'location' => 'Gaza City',
    ]);

    expect($workspace->fresh()->slug)->toContain('legacy-space')
        ->and($workspace->fresh()->location_id)->not->toBeNull()
        ->and($workspace->place)->not->toBeNull();
});

it('filters published workspaces by keyword price capacity amenities and featured', function () {
    $wifi = Amenity::factory()->create(['name' => 'WiFi Fast', 'slug' => 'wifi-fast']);
    $parking = Amenity::factory()->create(['name' => 'Parking Lot', 'slug' => 'parking-lot']);
    $location = Location::factory()->create(['city' => 'Ramallah']);

    $match = Workspace::factory()->featured()->create([
        'name' => 'Creative Hub',
        'price_per_hour' => 40,
        'capacity' => 20,
        'location_id' => $location->id,
        'status' => WorkspaceStatus::Published,
    ]);
    $match->amenities()->attach([$wifi->id, $parking->id]);

    Workspace::factory()->create([
        'name' => 'Other Room',
        'price_per_hour' => 120,
        'capacity' => 4,
        'status' => WorkspaceStatus::Published,
    ]);

    Workspace::factory()->draft()->create([
        'name' => 'Creative Draft',
        'price_per_hour' => 40,
        'capacity' => 20,
    ]);

    $this->get(route('spaces.index', [
        'search' => 'Creative',
        'min_price' => 30,
        'max_price' => 50,
        'capacity' => 10,
        'amenities' => $wifi->id.','.$parking->id,
        'featured' => 1,
        'city' => 'Ramallah',
    ]))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Workspaces/Index')
            ->has('spaces.data', 1)
            ->where('spaces.data.0.id', $match->id)
        );
});

it('eager loads relations on spaces index without n+1 style missing relations', function () {
    Workspace::factory()->count(3)->create();

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Workspaces/Index')
            ->has('spaces.data', 3)
            ->has('spaces.data.0.amenities')
            ->has('spaces.data.0.place')
        );
});

it('stores uploaded gallery images with strict validation and primary flag', function () {
    Storage::fake('public');
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->post(route('owner.workspaces.store'), [
        'name' => 'Gallery Space',
        'location' => 'Nablus',
        'description' => 'Nice room',
        'capacity' => 12,
        'price_per_hour' => 55,
        'status' => 'published',
        'payment_instructions' => 'Pay via bank transfer to IBAN PS00…',
        'payment_methods' => ['bank_transfer', 'cash'],
        'image' => UploadedFile::fake()->create('main.jpg', 100, 'image/jpeg'),
        'images' => [
            UploadedFile::fake()->create('two.jpg', 100, 'image/jpeg'),
        ],
    ]);

    $response->assertRedirect(route('owner.workspaces.index'));

    $workspace = Workspace::where('name', 'Gallery Space')->first();
    expect($workspace)->not->toBeNull()
        ->and($workspace->images()->count())->toBe(2)
        ->and($workspace->images()->where('is_primary', true)->count())->toBe(1)
        ->and($workspace->image_url)->not->toBeNull();
});

it('rejects invalid upload mime types', function () {
    Storage::fake('public');
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('owner.workspaces.store'), [
        'name' => 'Bad Upload',
        'location' => 'Hebron',
        'capacity' => 8,
        'price_per_hour' => 30,
        'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('image');
});

it('safely deletes gallery image files via service', function () {
    Storage::fake('public');
    $workspace = Workspace::factory()->create();
    $path = UploadedFile::fake()->create('room.jpg', 100, 'image/jpeg')->store('workspaces', 'public');

    $image = $workspace->images()->create([
        'path' => $path,
        'is_primary' => true,
        'sort_order' => 0,
    ]);

    app(WorkspaceGalleryService::class)->delete($image);

    Storage::disk('public')->assertMissing($path);
    expect(WorkspaceImage::whereKey($image->id)->exists())->toBeFalse();
});
