<?php

namespace App\Enums;

enum BookingMode: string
{
    case Seat = 'seat';
    case Whole = 'whole';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
