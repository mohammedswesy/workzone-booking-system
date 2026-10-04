<?php

namespace App\Models;

use App\Services\Offers\ActiveOfferResolver;
use App\Services\Pricing\BookingPricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'location',
        'capacity',
        'price_per_hour',
        'opening_time',
        'closing_time',
        'image_url',
    ];

    protected $appends = ['active_discount_percent', 'effective_price_per_hour', 'offer_label'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price_per_hour' => 'decimal:2',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function activeOffers(): HasMany
    {
        return $this->hasMany(Offer::class)->active();
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
