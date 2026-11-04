<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unpaid hold
    |--------------------------------------------------------------------------
    |
    | Minutes a pending subscription checkout stays open before
    | `billing:release-unpaid` cancels it and voids its subscription.
    |
    */
    'unpaid_hold_minutes' => (int) env('BILLING_UNPAID_HOLD_MINUTES', 30),
];
