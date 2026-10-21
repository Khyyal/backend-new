<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Client cancellation cutoff
    |--------------------------------------------------------------------------
    |
    | A client may cancel a reservation only up to this many hours before it
    | starts. Center staff are not bound by this cutoff.
    |
    */
    'client_cancellation_cutoff_hours' => (int) env('RESERVATIONS_CLIENT_CANCELLATION_CUTOFF_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Unpaid hold
    |--------------------------------------------------------------------------
    |
    | Minutes a pending online checkout keeps its reservations' places before
    | `reservations:release-unpaid` cancels it.
    |
    */
    'unpaid_hold_minutes' => (int) env('RESERVATIONS_UNPAID_HOLD_MINUTES', 30),
];
