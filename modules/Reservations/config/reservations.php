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
];
