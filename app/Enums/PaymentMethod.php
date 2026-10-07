<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case JawwalPay = 'jawwal_pay';
    case BankTransfer = 'bank_transfer';
    case OtherWallet = 'other_wallet';
    case Cash = 'cash';

    /** @deprecated Legacy workspace JSON value; treat as other_wallet. */
    case Wallet = 'wallet';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::JawwalPay->value,
            self::BankTransfer->value,
            self::OtherWallet->value,
            self::Cash->value,
        ];
    }

    public function isCash(): bool
    {
        return $this === self::Cash;
    }

    public function requiresReference(): bool
    {
        return ! $this->isCash();
    }
}
