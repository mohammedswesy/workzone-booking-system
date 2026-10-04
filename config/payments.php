<?php

return [
    'default' => env('PAYMENT_DEFAULT_PROVIDER', 'manual'),

    'currency' => env('PAYMENT_CURRENCY', env('PAYPAL_CURRENCY', 'USD')),

    'providers' => [
        'manual' => [
            'driver' => 'manual',
            'enabled' => env('PAYMENT_MANUAL_ENABLED', true),
        ],
        'paypal' => [
            'driver' => 'paypal',
            'enabled' => env('PAYMENT_PAYPAL_ENABLED', false),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],
    ],
];
