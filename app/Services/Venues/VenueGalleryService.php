<?php

namespace App\Services\Venues;

use App\Models\Venue;
use App\Models\VenueImage;
use App\Services\Media\ImageDerivativeService;
use App\Services\Media\SecureImageStore;
use App\Services\Media\SharedImagePathCleaner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VenueGalleryService
{
    public function __construct(
        private readonly SecureImageStore $secure,
        private readonly ImageDerivativeService $derivatives,
        private readonly SharedImagePathCleaner $cleaner,
    ) {}

    public function add(Venue $venue, UploadedFile $file, bool $primary = false, ?string $caption = null): VenueImage
    {
        $this->secure->assertSafeImage($file, 'images');

        // Re-encode (strip EXIF) on private disk, then publish the cleaned original.
        $tmp = $this->secure->store($file, 'venues/tmp', 'local', 'images');
        $tmpAbsolute = Storage::disk('local')->path($tmp['path']);
        $binary = file_get_contents($tmpAbsolute);
        Storage::disk('local')->delete($tmp['path']);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Failed to read processed image.');
        }

        $ext = pathinfo($tmp['path'], PATHINFO_EXTENSION) ?: 'jpg';
        $dest = 'venues/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('public')->put($dest, $binary);
        $this->derivatives->ensureForPath($dest);

        return DB::transaction(function () use ($venue, $dest, $primary, $caption) {
            $maxOrder = (int) $venue->images()->max('sort_order');
            if ($primary || ! $venue->images()->exists()) {
                $venue->images()->update(['is_primary' => false]);
                $primary = true;
            }

            return $venue->images()->create([
                'path' => $dest,
                'is_primary' => $primary,
                'sort_order' => $maxOrder + 1,
                'is_demo' => false,
                'caption' => $caption,
            ]);
        });
    }

    public function setPrimary(VenueImage $image): void
    {
        DB::transaction(function () use ($image) {
            $image->venue->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });
    }

    public function reorder(Venue $venue, array $orderedIds): void
    {
        DB::transaction(function () use ($venue, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                $venue->images()->whereKey($id)->update(['sort_order' => $index]);
            }
        });
    }

    public function delete(VenueImage $image): void
    {
        DB::transaction(function () use ($image) {
            $venue = $image->venue;
            $wasPrimary = $image->is_primary;
            $path = (string) $image->path;
            $image->delete();
            $this->derivatives->deleteDerivatives($path);
            $this->cleaner->deleteIfUnreferenced($path);

            if ($wasPrimary) {
                $next = $venue->images()->orderBy('sort_order')->first();
                if ($next) {
                    $next->update(['is_primary' => true]);
                }
            }
        });
    }

    public function updateCaption(VenueImage $image, ?string $caption): void
    {
        $image->update([
            'caption' => $caption !== null && $caption !== ''
                ? mb_substr($caption, 0, 255)
                : null,
        ]);
    }
}
