<?php

return [
    'default' => env('PAYMENT_DEFAULT_PROVIDER', 'manual'),

    'currency' => env('PAYMENT_CURRENCY', env('PAYPAL_CURRENCY', 'USD')),

    /** Default platform commission percent (0–100). Overridable per owner and via platform_settings. */
    'commission_percent' => (float) env('PAYMENT_COMMISSION_PERCENT', 0),

    /*
    |--------------------------------------------------------------------------
    | Ledger hold rules
    |--------------------------------------------------------------------------
    |
    | When true, owner earnings post only after the booking is completed AND paid.
    | "Pending" balance is the owner's share of paid-but-not-yet-completed bookings.
    |
    | cutover_at: payments confirmed before this instant never create ledger earnings
    | (pre-platform money never reached the platform). Default = first phase-10 migration day.
    | Overridable via platform_settings.ledger_cutover_at.
    |
    */
    'ledger' => [
        'available_requires_completed' => (bool) env('PAYMENT_LEDGER_REQUIRE_COMPLETED', true),
        'cutover_at' => env('PAYMENT_LEDGER_CUTOVER', '2026-10-04 00:00:00'),
    ],

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
