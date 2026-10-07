<?php

namespace App\Support;

class Money
{
    public static function of(string|float|int|null $value, int $scale = 2): string
    {
        return bcadd((string) ($value ?? '0'), '0', $scale);
    }

    public static function add(string $a, string $b, int $scale = 2): string
    {
        return bcadd(self::of($a, $scale), self::of($b, $scale), $scale);
    }

    public static function sub(string $a, string $b, int $scale = 2): string
    {
        return bcsub(self::of($a, $scale), self::of($b, $scale), $scale);
    }

    public static function mul(string $a, string $b, int $scale = 2): string
    {
        return bcmul(self::of($a, 4), self::of($b, 4), $scale);
    }

    public static function percentOf(string $amount, string $percent, int $scale = 2): string
    {
        $factor = bcdiv(self::of($percent, 4), '100', 6);

        return self::mul($amount, $factor, $scale);
    }

    public static function cmp(string $a, string $b, int $scale = 2): int
    {
        return bccomp(self::of($a, $scale), self::of($b, $scale), $scale);
    }

    public static function neg(string $amount, int $scale = 2): string
    {
        return self::mul($amount, '-1', $scale);
    }
}
