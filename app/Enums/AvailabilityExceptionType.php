<?php

namespace App\Enums;

enum AvailabilityExceptionType: string
{
    case Closed = 'closed';
    case SpecialHours = 'special_hours';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
