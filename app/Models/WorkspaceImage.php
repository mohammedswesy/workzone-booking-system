<?php

namespace App\Models;

use App\Services\Media\ImageDerivativeService;
use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WorkspaceImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'path',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected $appends = ['url', 'card_url', 'gallery_url'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function getUrlAttribute(): string
    {
        return PublicStorageUrl::fromPath($this->galleryRelativePath() ?? $this->path) ?? '';
    }

    public function getCardUrlAttribute(): string
    {
        return PublicStorageUrl::fromPath($this->cardRelativePath() ?? $this->path) ?? '';
    }

    public function getGalleryUrlAttribute(): string
    {
        return PublicStorageUrl::fromPath($this->galleryRelativePath() ?? $this->path) ?? '';
    }

    private function cardRelativePath(): ?string
    {
        return $this->existingDerivative('card');
    }

    private function galleryRelativePath(): ?string
    {
        return $this->existingDerivative('gallery');
    }

    private function existingDerivative(string $size): ?string
    {
        $relative = $this->relativePublicPath();
        if ($relative === null) {
            return null;
        }
        $path = app(ImageDerivativeService::class)->derivativeRelativePath($relative, $size);

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    public function deleteFile(): void
    {
        $relative = $this->relativePublicPath();
        if ($relative !== null) {
            app(\App\Services\Media\SharedImagePathCleaner::class)->deleteIfUnreferenced($relative);
        }
    }

    public function relativePublicPath(): ?string
    {
        $path = (string) $this->path;
        if (str_starts_with($path, 'workspaces/')) {
            return $path;
        }
        if (str_starts_with($path, '/storage/workspaces/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }
        if (preg_match('#/storage/(workspaces/.+)$#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
