<?php

namespace App\Services\Venues;

use App\Enums\VenueStatus;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Services\Media\ImageDerivativeService;
use App\Services\Media\SharedImagePathCleaner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attach / remove fictional demo gallery images for venues.
 */
class DemoVenueImageService
{
    public function __construct(
        private readonly ImageDerivativeService $derivatives,
        private readonly SharedImagePathCleaner $cleaner,
    ) {}

    /**
     * @return array{note?: string, by_unit_type: array<string, list<string>>, palettes: list<string>, files: list<string>}
     */
    public function manifest(): array
    {
        $path = database_path('seeders/demo-images/manifest.json');
        $json = File::get($path);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function poolPath(string $filename): string
    {
        return database_path('seeders/demo-images/'.$filename);
    }

    /**
     * Deterministic scene list for a venue based on its unit types.
     *
     * @return list<string>
     */
    public function scenesForVenue(Venue $venue): array
    {
        $manifest = $this->manifest();
        $byType = $manifest['by_unit_type'] ?? [];
        $venue->loadMissing('units');

        $scenes = [];
        foreach ($venue->units as $unit) {
            $type = $unit->type?->value ?? (string) $unit->type;
            $type = $type !== '' ? $type : 'other';
            foreach ($byType[$type] ?? $byType['other'] ?? [] as $scene) {
                $scenes[] = $scene;
            }
        }

        if ($scenes === []) {
            $scenes = $byType['other'] ?? ['coworking', 'lounge', 'reception'];
        }

        // Keep order, unique — then rotate by venue id so neighbors differ.
        $unique = array_values(array_unique($scenes));
        $offset = $venue->id % max(1, count($unique));

        return array_values([
            ...array_slice($unique, $offset),
            ...array_slice($unique, 0, $offset),
        ]);
    }

    public function paletteForVenue(Venue $venue): string
    {
        $palettes = $this->manifest()['palettes'] ?? ['teal', 'sand', 'slate'];
        $index = $venue->id % count($palettes);

        return $palettes[$index];
    }

    /**
     * @return array{attached: int, skipped: bool, reason?: string, files: list<string>}
     */
    public function attachForVenue(Venue $venue, int $targetCount = 3, bool $dryRun = false): array
    {
        if (! in_array($venue->status, [VenueStatus::Published, VenueStatus::Draft], true)) {
            return ['attached' => 0, 'skipped' => true, 'reason' => 'status', 'files' => []];
        }

        $current = $venue->images()->count();
        if ($current >= $targetCount) {
            return ['attached' => 0, 'skipped' => true, 'reason' => 'enough', 'files' => []];
        }

        $need = $targetCount - $current;
        $scenes = $this->scenesForVenue($venue);
        $palette = $this->paletteForVenue($venue);
        $files = [];

        $maxOrder = (int) $venue->images()->max('sort_order');
        $hasPrimary = $venue->images()->where('is_primary', true)->exists();

        for ($i = 0; $i < $need; $i++) {
            $scene = $scenes[$i % count($scenes)];
            $filename = "{$scene}-{$palette}.jpg";
            $source = $this->poolPath($filename);
            if (! is_file($source)) {
                continue;
            }

            $files[] = $filename;
            if ($dryRun) {
                continue;
            }

            $destName = Str::uuid()->toString().'.jpg';
            $destPath = 'venues/demo/'.$destName;
            Storage::disk('public')->put($destPath, File::get($source));
            $this->derivatives->ensureForPath($destPath);

            $isPrimary = ! $hasPrimary && $i === 0;
            if ($isPrimary) {
                $venue->images()->update(['is_primary' => false]);
                $hasPrimary = true;
            }

            $venue->images()->create([
                'path' => $destPath,
                'is_primary' => $isPrimary,
                'sort_order' => $maxOrder + $i + 1,
                'is_demo' => true,
                'caption' => null,
            ]);
        }

        return [
            'attached' => count($files),
            'skipped' => false,
            'files' => $files,
        ];
    }

    /**
     * @return array{rows: int, files_deleted: int}
     */
    public function removeAllDemoImages(?string $venueSlug = null): array
    {
        $query = VenueImage::query()->where('is_demo', true);
        if ($venueSlug) {
            $query->whereHas('venue', fn ($q) => $q->where('slug', $venueSlug));
        }

        $rows = 0;
        $filesDeleted = 0;

        $query->orderBy('id')->chunkById(50, function ($images) use (&$rows, &$filesDeleted) {
            foreach ($images as $image) {
                /** @var VenueImage $image */
                $path = (string) $image->path;
                $wasPrimary = $image->is_primary;
                $venue = $image->venue;
                $image->delete();
                $rows++;

                $this->derivatives->deleteDerivatives($path);
                if ($this->cleaner->deleteIfUnreferenced($path)) {
                    $filesDeleted++;
                }

                if ($wasPrimary && $venue) {
                    $next = $venue->images()->orderBy('sort_order')->first();
                    if ($next) {
                        $venue->images()->update(['is_primary' => false]);
                        $next->update(['is_primary' => true]);
                    }
                }
            }
        });

        return ['rows' => $rows, 'files_deleted' => $filesDeleted];
    }

    public function demoImageCount(): int
    {
        return VenueImage::query()->where('is_demo', true)->count();
    }
}
