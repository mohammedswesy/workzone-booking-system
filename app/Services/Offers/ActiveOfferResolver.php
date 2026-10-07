<?php

namespace App\Services\Offers;

use App\Models\Offer;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the active discount on a bookable unit (Workspace).
 *
 * Precedence: most specific wins — a unit offer overrides any venue offer
 * and is never stacked with it (even when the unit percent is lower).
 */
class ActiveOfferResolver
{
    public function for(Workspace $workspace, ?Carbon $at = null): ?Offer
    {
        $at ??= now();

        $unitOffer = $this->bestUnitOffer($workspace, $at);
        if ($unitOffer !== null) {
            return $unitOffer;
        }

        return $this->bestVenueOffer($workspace, $at);
    }

    private function bestUnitOffer(Workspace $workspace, Carbon $at): ?Offer
    {
        if ($workspace->relationLoaded('activeOffers')) {
            return $workspace->activeOffers
                ->filter(fn (Offer $offer) => $offer->workspace_id && $this->isValidPercent($offer))
                ->sortByDesc('discount_percent')
                ->first();
        }

        return $workspace->offers()
            ->active($at)
            ->whereNotNull('workspace_id')
            ->where('discount_percent', '>', 0)
            ->where('discount_percent', '<=', 100)
            ->orderByDesc('discount_percent')
            ->first();
    }

    private function bestVenueOffer(Workspace $workspace, Carbon $at): ?Offer
    {
        $venueId = $workspace->venue_id;
        if (! $venueId) {
            return null;
        }

        if ($workspace->relationLoaded('venue') && $workspace->venue?->relationLoaded('offers')) {
            return $workspace->venue->offers
                ->filter(fn (Offer $offer) => $offer->venue_id
                    && $offer->is_active
                    && $this->isValidPercent($offer)
                    && ($offer->starts_at === null || $offer->starts_at <= $at)
                    && ($offer->ends_at === null || $offer->ends_at >= $at))
                ->sortByDesc('discount_percent')
                ->first();
        }

        return Offer::query()
            ->where('venue_id', $venueId)
            ->whereNull('workspace_id')
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
