<?php

namespace App\Services\Pricing;

use App\Enums\BookingMode;
use App\Models\Offer;
use App\Models\Workspace;
use App\Services\Offers\ActiveOfferResolver;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BookingPricingService
{
    public function __construct(
        private readonly ActiveOfferResolver $offers,
    ) {}

    public function quote(
        Workspace $workspace,
        Carbon $startAt,
        Carbon $endAt,
        ?Offer $offer = null,
        int $seats = 1,
    ): BookingPriceQuote {
        if ($endAt->lte($startAt)) {
            throw new InvalidArgumentException('End time must be after start time.');
        }

        $inventorySeats = max(1, $seats);

        $seconds = $startAt->diffInSeconds($endAt);
        $hours = bcdiv((string) $seconds, '3600', 4);

        if (bccomp($hours, '0', 4) !== 1) {
            throw new InvalidArgumentException('Booking duration must be greater than zero.');
        }

        // Whole mode: flat room rate (price × hours). Seats are inventory-only.
        // Seat mode: price × hours × seats.
        $billableSeats = $workspace->booking_mode === BookingMode::Whole
            ? 1
            : $inventorySeats;

        $pricePerHour = $this->money((string) $workspace->price_per_hour);
        $seatHours = bcmul($hours, (string) $billableSeats, 4);
        $baseAmount = $this->money(bcmul($pricePerHour, $seatHours, 4));

        $offer ??= $this->offers->for($workspace, $startAt);
        $percent = 0;
        $offerId = null;

        if ($offer && $offer->discount_percent > 0 && $offer->discount_percent <= 100) {
            $percent = (int) $offer->discount_percent;
            $offerId = $offer->id;
        }

        $discountAmount = '0.00';
        if ($percent > 0) {
            $discountAmount = $this->money(
                bcmul($baseAmount, bcdiv((string) $percent, '100', 4), 4)
            );
        }

        $finalAmount = $this->money(bcsub($baseAmount, $discountAmount, 4));

        return new BookingPriceQuote(
            baseAmount: $baseAmount,
            discountPercent: $percent,
            discountAmount: $discountAmount,
            finalAmount: $finalAmount,
            hours: $this->money($hours, 2),
            pricePerHour: $this->money($pricePerHour),
            offerId: $offerId,
            seats: $inventorySeats,
        );
    }

    private function money(string $value, int $scale = 2): string
    {
        return bcadd($value, '0', $scale);
    }
}
