<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pending booking timeout (minutes)
    |--------------------------------------------------------------------------
    |
    | Pending bookings older than this are expired to cancelled by the scheduler.
    |
    */
    'pending_timeout_minutes' => (int) env('BOOKING_PENDING_TIMEOUT_MINUTES', 30),
];
