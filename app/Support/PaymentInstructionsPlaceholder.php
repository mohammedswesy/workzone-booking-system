<?php

namespace App\Support;

/**
 * Neutral placeholder used when published workspaces predate real payment setup.
 * Stored in English; Arabic is recognized for detection/display localization.
 */
final class PaymentInstructionsPlaceholder
{
    public const EN = 'Contact the owner to confirm the payment method.';

    public const AR = 'تواصل مع المالك لتأكيد طريقة الدفع.';

    /**
     * Legacy copy from the first backfill (must not be treated as real details).
     */
    public const LEGACY_EN = "Contact the space owner to arrange payment (bank transfer, wallet, or cash).\nPlease update these instructions with your real payment details.";

    /**
     * @return list<string>
     */
    public static function knownValues(): array
    {
        return [
            self::EN,
            self::AR,
            self::LEGACY_EN,
        ];
    }

    public static function isPlaceholder(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return true;
        }

        foreach (self::knownValues() as $known) {
            if ($normalized === trim($known)) {
                return true;
            }
        }

        return false;
    }

    public static function forLocale(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return str_starts_with(strtolower((string) $locale), 'ar') ? self::AR : self::EN;
    }
}
