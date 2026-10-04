<?php

namespace App\Models;

use App\Enums\WorkspaceStatus;
use App\Services\Offers\ActiveOfferResolver;
use App\Services\Pricing\BookingPricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'location',
        'location_id',
        'description',
        'capacity',
        'price_per_hour',
        'opening_time',
        'closing_time',
        'image_url',
        'status',
        'featured',
    ];

    protected $appends = ['active_discount_percent', 'effective_price_per_hour', 'offer_label'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price_per_hour' => 'decimal:2',
            'status' => WorkspaceStatus::class,
            'featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = static::uniqueSlug($workspace->name);
            }
        });

        static::updating(function (Workspace $workspace) {
            if ($workspace->isDirty('name') && empty($workspace->slug)) {
                $workspace->slug = static::uniqueSlug($workspace->name, $workspace->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 1;

        while (static::query()
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Normalized location record (column `location` remains a legacy string label).
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function activeOffers(): HasMany
    {
        return $this->hasMany(Offer::class)->active();
    }

    public function images(): HasMany
    {
        return $this->hasMany(WorkspaceImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(WorkspaceImage::class)->where('is_primary', true);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', WorkspaceStatus::Published);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function getActiveDiscountPercentAttribute(): int
    {
        $offer = app(ActiveOfferResolver::class)->for($this);

        return (int) ($offer?->discount_percent ?? 0);
    }

    public function getEffectivePricePerHourAttribute(): float
    {
        $start = now();
        $end = $start->copy()->addHour();

        try {
            $quote = app(BookingPricingService::class)->quote($this, $start, $end);

            return (float) $quote->finalAmount;
        } catch (\Throwable) {
            return (float) $this->price_per_hour;
        }
    }

    public function getOfferLabelAttribute(): ?string
    {
        $d = $this->active_discount_percent;

        return $d > 0 ? ('خصم '.$d.'%') : null;
    }

    public function openingCarbonOn(Carbon $day): Carbon
    {
        return Carbon::parse($day->toDateString().' '.$this->opening_time);
    }

    public function closingCarbonOn(Carbon $day): Carbon
    {
        return Carbon::parse($day->toDateString().' '.$this->closing_time);
    }
}
