<?php

namespace App\Services\Media;

use App\Models\VenueImage;
use App\Models\WorkspaceImage;
use Illuminate\Support\Facades\Storage;

/**
 * Delete a storage file only when no venue_images / workspace_images row still references the path.
 */
class SharedImagePathCleaner
{
    public function deleteIfUnreferenced(string $path): bool
    {
        $normalized = $this->normalize($path);
        if ($normalized === null) {
            return false;
        }

        $stillUsed = WorkspaceImage::query()->where('path', $normalized)->exists()
            || WorkspaceImage::query()->where('path', '/storage/'.$normalized)->exists()
            || VenueImage::query()->where('path', $normalized)->exists()
            || VenueImage::query()->where('path', '/storage/'.$normalized)->exists();

        if ($stillUsed) {
            return false;
        }

        return Storage::disk('public')->delete($normalized);
    }

    public function normalize(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'workspaces/') || str_starts_with($path, 'venues/')) {
            return $path;
        }
        if (preg_match('#/storage/(workspaces/.+|venues/.+)$#', $path, $m)) {
            return $m[1];
        }
        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        return $path;
    }
}
