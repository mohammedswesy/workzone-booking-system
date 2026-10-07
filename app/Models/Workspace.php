<?php

namespace App\Models;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Services\Offers\ActiveOfferResolver;
use App\Services\Pricing\BookingPricingService;
use App\Support\PaymentInstructionsPlaceholder;
use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Workspace = bookable unit (room/desk) inside a Venue (building).
 * Public catalog lists venues; bookings always target a workspace id.
 */
class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'venue_id',
        'name',
        'slug',
        'location',
        'location_id',
        'description',
        'capacity',
        'booking_mode',
        'type',
        'price_per_hour',
        /** @deprecated Prefer venue_hours / unit_hours via AvailabilityService */
        'opening_time',
        /** @deprecated Prefer venue_hours / unit_hours via AvailabilityService */
        'closing_time',
        'inherits_venue_hours',
        'bookings_paused',
        'bookings_paused_note',
        'image_url',
        'status',
        'featured',
        'payment_instructions',
        'payment_methods',
    ];

    protected $appends = [
        'active_discount_percent',
        'effective_price_per_hour',
        'offer_label',
        'cover_image_url',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'booking_mode' => BookingMode::class,
            'type' => WorkspaceType::class,
            'price_per_hour' => 'decimal:2',
            'status' => WorkspaceStatus::class,
            'featured' => 'boolean',
            'inherits_venue_hours' => 'boolean',
            'bookings_paused' => 'boolean',
            'payment_methods' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function acceptsPaymentMethod(string $method): bool
    {
        // Legacy helper — guest checkout uses platform payment methods instead.
        return in_array($method, $this->payment_methods ?? [], true);
    }

    public function hasPlaceholderPaymentInstructions(): bool
    {
        return PaymentInstructionsPlaceholder::isPlaceholder($this->payment_instructions);
    }

    /**
     * Per-workspace payment setup banners are retired; platform methods are admin-managed.
     */
    public function needsOwnerPaymentSetup(): bool
    {
        return false;
    }

    public function hasActiveFutureBookings(): bool
    {
        return $this->bookings()
            ->whereIn('status', BookingStatus::blocking())
            ->where('end_at', '>', now())
            ->exists();
    }

    /**
     * Future pending/confirmed bookings block archiving (amendment d).
     */
    public function hasFuturePendingOrConfirmedBookings(): bool
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->where('end_at', '>', now())
            ->exists();
    }

    /**
     * Instructions safe to show bookers (null when still a placeholder).
     */
    public function bookerPaymentInstructions(): ?string
    {
        if ($this->hasPlaceholderPaymentInstructions()) {
            return null;
        }

        $value = trim((string) $this->payment_instructions);

        return $value !== '' ? $value : null;
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

    public function hours(): HasMany
    {
        return $this->hasMany(UnitHour::class)->orderBy('weekday');
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class)
            ->where('scope', 'unit')
            ->orderBy('starts_on');
    }

    public function activeOffers(): HasMany
    {
        return $this->hasMany(Offer::class)->active();
    }

    public function images(): HasMany
    {
        return $this->hasMany(WorkspaceImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(WorkspaceImage::class)->where('is_primary', true);
    }

    /**
     * Single cover URL for cards/details/dashboards (relative /storage/... when local).
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $primary = $this->images->firstWhere('is_primary', true)
                ?? $this->images->sortBy('sort_order')->first();

            return $primary?->card_url ?: ($primary?->url ?: null);
        }

        return PublicStorageUrl::fromPath($this->attributes['image_url'] ?? null);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', WorkspaceStatus::Published)
            ->whereHas('owner', fn (Builder $owner) => $owner->where('is_active', true));
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
        $service = app(\App\Services\Availability\AvailabilityService::class);
        $tz = $service->timezoneFor($this);
        $localDay = $day->copy()->timezone($tz);
        $intervals = $service->openIntervalsForLocalDay($this, $localDay->toDateString());
        if ($intervals !== []) {
            return $intervals[0]['open']->copy();
        }

        // Fallback for pre-backfill rows (deprecated columns).
        return Carbon::parse($localDay->toDateString().' '.($this->opening_time ?: '08:00:00'), $tz);
    }

    public function closingCarbonOn(Carbon $day): Carbon
    {
        $service = app(\App\Services\Availability\AvailabilityService::class);
        $tz = $service->timezoneFor($this);
        $localDay = $day->copy()->timezone($tz);
        $intervals = $service->openIntervalsForLocalDay($this, $localDay->toDateString());
        if ($intervals !== []) {
            return $intervals[0]['close']->copy();
        }

        return Carbon::parse($localDay->toDateString().' '.($this->closing_time ?: '22:00:00'), $tz);
    }
}
