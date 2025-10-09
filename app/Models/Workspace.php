<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id','name','location','capacity','price_per_hour','image_url'];

    // نخلي القيم المحسوبة ترجع تلقائيًا في JSON/Array
    protected $appends = ['active_discount_percent', 'effective_price_per_hour', 'offer_label'];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function offers()
    {
        return $this->hasMany(\App\Models\Offer::class);
    }

    public function activeOffers()
    {
        return $this->hasMany(\App\Models\Offer::class)->active();
    }

    public function getActiveDiscountPercentAttribute(): int
    {
        if ($this->relationLoaded('activeOffers')) {
            return (int) ($this->activeOffers->max('discount_percent') ?? 0);
        }

        return (int) ($this->activeOffers()->max('discount_percent') ?? 0);
    }

    /**
     * السعر الفعلي بعد تطبيق أعلى خصم.
     */
    public function getEffectivePricePerHourAttribute(): float
    {
        $d = $this->active_discount_percent ?? $this->getActiveDiscountPercentAttribute();

        if ($d > 0) {
            return round($this->price_per_hour * (1 - ($d / 100)), 2);
        }

        return (float) $this->price_per_hour;
    }

    // ليبل لطيف يظهر في الواجهة (اختياري)
    public function getOfferLabelAttribute(): ?string
    {
        $d = $this->active_discount_percent ?? 0;
        return $d > 0 ? ('خصم ' . $d . '%') : null;
    }
}
