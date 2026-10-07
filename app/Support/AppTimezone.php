<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AppTimezone
{
    public static function storage(): string
    {
        return 'UTC';
    }

    public static function display(): string
    {
        return (string) config('app.display_timezone', 'Asia/Gaza');
    }

    public static function now(): CarbonInterface
    {
        return Carbon::now(self::storage());
    }

    public static function toDisplay(?CarbonInterface $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        return $value->copy()->timezone(self::display());
    }

    public static function formatDisplay(?CarbonInterface $value, string $format = 'Y-m-d H:i'): ?string
    {
        $local = self::toDisplay($value);

        return $local?->format($format);
    }

    /**
     * Interpret a naive wall-clock input (datetime-local) in the display timezone, return UTC.
     */
    public static function parseInput(string|CarbonInterface $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->utc()->seconds(0);
        }

        $raw = trim(str_replace('T', ' ', $value));
        // Strip trailing timezone designators if a client ever sends them — wall clock wins.
        $raw = preg_replace('/(Z|[+-]\d{2}:?\d{2})$/', '', $raw) ?? $raw;
        $raw = trim($raw);

        return Carbon::parse($raw, self::display())->utc()->seconds(0);
    }

    /**
     * ISO-8601 in UTC with explicit +00:00 — never hand-format a local clock as UTC.
     */
    public static function utcIso(?CarbonInterface $value = null): string
    {
        $moment = ($value ?? self::now())->copy()->utc();

        return $moment->toIso8601String();
    }
}
