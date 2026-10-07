<?php

namespace App\Enums;

enum AvailabilityScope: string
{
    case Venue = 'venue';
    case Unit = 'unit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
