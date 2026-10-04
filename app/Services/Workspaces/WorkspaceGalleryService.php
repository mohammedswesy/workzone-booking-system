<?php

namespace App\Services\Workspaces;

use App\Models\Workspace;
use App\Models\WorkspaceImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkspaceGalleryService
{
    public function add(Workspace $workspace, UploadedFile $file, bool $primary = false): WorkspaceImage
    {
        $path = $file->store('workspaces', 'public');

        return DB::transaction(function () use ($workspace, $path, $primary) {
            $maxOrder = (int) $workspace->images()->max('sort_order');

            if ($primary || ! $workspace->images()->exists()) {
                $workspace->images()->update(['is_primary' => false]);
                $primary = true;
            }

            $image = $workspace->images()->create([
                'path' => $path,
                'is_primary' => $primary,
                'sort_order' => $maxOrder + 1,
            ]);

            if ($primary) {
                $this->syncLegacyImageUrl($workspace, $image);
            }

            return $image;
        });
    }

    public function setPrimary(WorkspaceImage $image): void
    {
        DB::transaction(function () use ($image) {
            $image->workspace->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
            $this->syncLegacyImageUrl($image->workspace, $image);
        });
    }

    public function reorder(Workspace $workspace, array $orderedIds): void
    {
        DB::transaction(function () use ($workspace, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                $workspace->images()
                    ->whereKey($id)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    public function delete(WorkspaceImage $image): void
    {
        DB::transaction(function () use ($image) {
            $workspace = $image->workspace;
            $wasPrimary = $image->is_primary;

            $image->deleteFile();
            $image->delete();

            if ($wasPrimary) {
                $next = $workspace->images()->orderBy('sort_order')->first();
                if ($next) {
                    $next->update(['is_primary' => true]);
                    $this->syncLegacyImageUrl($workspace, $next);
                } else {
                    $workspace->update(['image_url' => null]);
                }
            }
        });
    }

    private function syncLegacyImageUrl(Workspace $workspace, WorkspaceImage $image): void
    {
        $url = str_starts_with($image->path, 'http') || str_starts_with($image->path, '/storage/')
            ? $image->path
            : Storage::disk('public')->url($image->path);

        $workspace->update(['image_url' => $url]);
    }
}
