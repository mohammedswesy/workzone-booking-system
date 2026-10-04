<?php

namespace App\Support;

class PaymentsConfig
{
    public static function paypalAvailable(): bool
    {
        if (! (bool) config('payments.providers.paypal.enabled')) {
            return false;
        }

        $mode = (string) config('paypal.mode', 'sandbox');
        if (! in_array($mode, ['sandbox', 'live'], true)) {
            $mode = 'sandbox';
        }

        $clientId = trim((string) config("paypal.{$mode}.client_id", ''));
        $clientSecret = trim((string) config("paypal.{$mode}.client_secret", ''));

        return $clientId !== '' && $clientSecret !== '';
    }
}
