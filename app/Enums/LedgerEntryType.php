<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Earning = 'earning';
    case Commission = 'commission';
    case Refund = 'refund';
    case Payout = 'payout';
    case Adjustment = 'adjustment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
