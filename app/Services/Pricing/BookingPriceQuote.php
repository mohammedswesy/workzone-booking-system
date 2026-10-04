<?php

namespace App\Services\Pricing;

final class BookingPriceQuote
{
    public function __construct(
        public readonly string $baseAmount,
        public readonly int $discountPercent,
        public readonly string $discountAmount,
        public readonly string $finalAmount,
        public readonly string $hours,
        public readonly string $pricePerHour,
        public readonly ?int $offerId = null,
        public readonly int $seats = 1,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base_amount' => $this->baseAmount,
            'discount_percent' => $this->discountPercent,
            'discount_amount' => $this->discountAmount,
            'final_amount' => $this->finalAmount,
            'hours' => $this->hours,
            'price_per_hour' => $this->pricePerHour,
            'offer_id' => $this->offerId,
            'seats' => $this->seats,
        ];
    }
}
