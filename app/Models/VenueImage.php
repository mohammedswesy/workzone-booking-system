<?php

namespace App\Models;

use App\Services\Media\ImageDerivativeService;
use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Gallery image for a Venue (building). Paths may be shared with workspace_images rows.
 */
class VenueImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'path',
        'is_primary',
        'sort_order',
        'is_demo',
        'caption',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_demo' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected $appends = ['url', 'card_url', 'gallery_url'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
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
        if (! $this->path) {
            return null;
        }
        $path = app(ImageDerivativeService::class)->derivativeRelativePath((string) $this->path, $size);

        return Storage::disk('public')->exists($path) ? $path : null;
    }
}
