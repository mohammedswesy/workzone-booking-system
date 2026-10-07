<?php

namespace App\Services\Availability;

use Illuminate\Support\Carbon;

/**
 * Result of checking whether a booking window is allowed.
 *
 * v1 rule documented: bookings must fit entirely inside one open interval
 * of one local day (no midnight crossing).
 */
final class AvailabilityDecision
{
    /**
     * @param  list<array{open: Carbon, close: Carbon}>  $intervals
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reasonKey = null,
        public readonly array $reasonReplace = [],
        public readonly array $intervals = [],
        public readonly ?string $timezone = null,
    ) {}

    public static function ok(array $intervals, string $timezone): self
    {
        return new self(true, null, [], $intervals, $timezone);
    }

    public static function deny(string $reasonKey, array $replace = [], string $timezone = 'Asia/Gaza'): self
    {
        return new self(false, $reasonKey, $replace, [], $timezone);
    }

    public function localizedMessage(?string $locale = null): string
    {
        if ($this->allowed || $this->reasonKey === null) {
            return '';
        }

        return (string) __('availability.'.$this->reasonKey, $this->reasonReplace, $locale);
    }
}
