<?php

namespace App\Models;

use App\Enums\VenueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Venue = building / listing.
 * Workspace (unit) = bookable room or desk inside a venue.
 */
class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'location_id',
        'address',
        'status',
        'featured',
        'timezone',
        'bookings_paused',
        'bookings_paused_note',
    ];

    protected $appends = [
        'cover_image_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => VenueStatus::class,
            'featured' => 'boolean',
            'bookings_paused' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (Venue $venue) {
            if (blank($venue->slug)) {
                $venue->slug = static::uniqueSlugFrom($venue->name);
            }
            $venue->slug = static::ensureSlugHasNonDigit($venue->slug);
        });

        static::created(function (Venue $venue) {
            app(\App\Services\Availability\VenueHoursEnsuring::class)->ensure($venue);
        });

        static::updating(function (Venue $venue) {
            if ($venue->isDirty('slug')) {
                $venue->slug = static::ensureSlugHasNonDigit($venue->slug);
                $old = $venue->getOriginal('slug');
                if (is_string($old) && $old !== '' && $old !== $venue->slug) {
                    VenueSlugRedirect::query()->firstOrCreate([
                        'slug' => $old,
                    ], [
                        'venue_id' => $venue->id,
                    ]);
                }
            }
        });
    }

    public static function uniqueSlugFrom(string $name): string
    {
        $base = static::ensureSlugHasNonDigit(Str::slug($name) ?: 'venue');
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public static function ensureSlugHasNonDigit(string $slug): string
    {
        $slug = trim($slug, '-');
        if ($slug === '' || preg_match('/^\d+$/', $slug)) {
            return 'venue-'.($slug !== '' ? $slug : Str::lower(Str::random(6)));
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Workspace::class, 'venue_id');
    }

    /** @deprecated Use units() — Workspace model is the bookable unit. */
    public function workspaces(): HasMany
    {
        return $this->units();
    }

    public function images(): HasMany
    {
        return $this->hasMany(VenueImage::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_venue')->withTimestamps();
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(VenueHour::class)->orderBy('weekday');
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class)
            ->where('scope', 'venue')
            ->orderBy('starts_on');
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(VenueSlugRedirect::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', VenueStatus::Published)
            ->whereHas('owner', fn (Builder $owner) => $owner->where('is_active', true));
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    /**
     * Venues with no usable map pin (null coords or Null Island 0,0).
     */
    public function scopeWithoutCoordinates(Builder $query): Builder
    {
        return app(\App\Services\Venues\VenueLocationService::class)
            ->applyWithoutCoordinates($query);
    }

    public function hasCoordinates(): bool
    {
        return app(\App\Services\Venues\VenueLocationService::class)->hasCoordinates($this);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $primary = $this->images->firstWhere('is_primary', true)
                ?? $this->images->sortBy('sort_order')->first();

            // Prefer card-sized derivative for listing cards when available.
            return $primary?->card_url ?: ($primary?->url ?: null);
        }

        return null;
    }
}
