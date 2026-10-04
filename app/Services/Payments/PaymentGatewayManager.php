<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function driver(PaymentProvider|string $provider): PaymentGateway
    {
        $key = $provider instanceof PaymentProvider ? $provider->value : $provider;

        return match ($key) {
            PaymentProvider::Manual->value => app(ManualPaymentGateway::class),
            PaymentProvider::Paypal->value => app(PaypalPaymentGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment provider [{$key}]."),
        };
    }
}
