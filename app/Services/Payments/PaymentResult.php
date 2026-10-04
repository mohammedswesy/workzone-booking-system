<?php

namespace App\Services\Payments;

use App\Models\Payment;

final class PaymentResult
{
    public function __construct(
        public readonly Payment $payment,
        public readonly ?string $redirectUrl = null,
        public readonly string $message = 'OK',
    ) {}
}
