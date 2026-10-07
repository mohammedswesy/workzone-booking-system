<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Built in one place and applied by SecurityHeaders middleware.
    | Set CSP_REPORT_ONLY=true to roll out without enforcing.
    |
    */

    'report_only' => (bool) env('CSP_REPORT_ONLY', false),

    /*
    | Base directives (production / non-Vite). script-src never includes
    | 'unsafe-eval'. Inline bootstrap scripts use a per-request nonce.
    */
    'directives' => [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "font-src 'self' data:",
        "img-src 'self' data: blob: ".env(
            'MAP_TILE_HOST',
            'https://tile.openstreetmap.org https://a.tile.openstreetmap.org https://b.tile.openstreetmap.org https://c.tile.openstreetmap.org'
        ),
        "connect-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
    ],

    /*
    | When public/hot exists (vite dev), allow the Vite HMR origin for scripts,
    | styles, fonts, images, and websocket connect.
    */
    'vite_dev_origin' => env('VITE_DEV_ORIGIN', 'http://127.0.0.1:5173'),
];
