<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Approved], true);
    }
}
