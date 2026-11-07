<?php

return [

    /*
     * Days after an online payment succeeds before the center can be
     * paid for it, so refunds and chargebacks can land first.
     */
    'hold_days' => (int) env('PAYOUT_HOLD_DAYS', 7),

];
