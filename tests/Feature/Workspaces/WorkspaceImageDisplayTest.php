<?php

use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceGalleryService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('stores uploaded workspace images on the public disk', function () {
    $workspace = Workspace::factory()->create(['image_url' => null]);
    $file = UploadedFile::fake()->create('desk.jpg', 100, 'image/jpeg');

    $image = app(WorkspaceGalleryService::class)->add($workspace, $file);

    Storage::disk('public')->assertExists($image->path);
    expect($image->path)->toStartWith('workspaces/');
});

it('marks the first uploaded image as primary automatically', function () {
    $workspace = Workspace::factory()->create(['image_url' => null]);

    $first = app(WorkspaceGalleryService::class)->add(
        $workspace,
        UploadedFile::fake()->create('one.jpg', 100, 'image/jpeg'),
    );
    $second = app(WorkspaceGalleryService::class)->add(
        $workspace,
        UploadedFile::fake()->create('two.jpg', 100, 'image/jpeg'),
    );

    expect($first->fresh()->is_primary)->toBeTrue()
        ->and($second->fresh()->is_primary)->toBeFalse()
        ->and($workspace->fresh()->images()->where('is_primary', true)->count())->toBe(1);
});

it('exposes a relative cover_image_url that does not depend on APP_URL', function () {
    config(['app.url' => 'http://wrong-host.example:9999']);
    config(['filesystems.disks.public.url' => '/storage']);

    $workspace = Workspace::factory()->create(['image_url' => null]);
    $image = app(WorkspaceGalleryService::class)->add(
        $workspace,
        UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        primary: true,
    );

    $workspace->refresh()->load('images');
    $image->refresh();

    expect($image->url)->toStartWith('/storage/workspaces/')
        ->and($workspace->image_url)->toStartWith('/storage/workspaces/')
        ->and($workspace->cover_image_url)->toBe($image->card_url ?: $image->url)
        ->and($workspace->cover_image_url)->not->toContain('wrong-host.example')
        ->and(PublicStorageUrl::fromPath($image->path))->toBe('/storage/'.$image->path);
});

it('returns a null cover_image_url placeholder signal when no image exists', function () {
    $workspace = Workspace::factory()->create(['image_url' => null]);

    expect($workspace->fresh()->cover_image_url)->toBeNull()
        ->and(PublicStorageUrl::fromPath(null))->toBeNull();
});

it('normalizes legacy absolute APP_URL image_url values to relative storage paths', function () {
    $workspace = Workspace::factory()->create([
        'image_url' => 'http://localhost/storage/workspaces/legacy.png',
    ]);

    expect($workspace->cover_image_url)->toBe('/storage/workspaces/legacy.png');
});

it('drops third-party absolute image urls so CSP img-src self stays intact', function () {
    $workspace = Workspace::factory()->create([
        'image_url' => 'https://via.placeholder.com/640x480.png',
    ]);

    expect($workspace->cover_image_url)->toBeNull();
});
