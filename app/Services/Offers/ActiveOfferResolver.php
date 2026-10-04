<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

class ActiveOfferResolver
{
    public function for(Workspace $workspace, ?Carbon $at = null): ?Offer
    {
        $at ??= now();

        if ($workspace->relationLoaded('activeOffers')) {
            return $workspace->activeOffers
                ->filter(fn (Offer $offer) => $this->isValidPercent($offer))
                ->sortByDesc('discount_percent')
                ->first();
        }

        return $workspace->offers()
            ->active($at)
            ->where('discount_percent', '>', 0)
            ->where('discount_percent', '<=', 100)
            ->orderByDesc('discount_percent')
            ->first();
    }

    private function isValidPercent(Offer $offer): bool
    {
        return $offer->discount_percent > 0 && $offer->discount_percent <= 100;
    }
}
