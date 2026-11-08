<?php

return [
    /*
     * Days the nightly build recomputes, ending yesterday. Purchases, bookings
     * and payments change status after the day they were made (cancellations,
     * completions, no-shows, late payments), so recent days are restated.
     */
    'restate_days' => (int) env('ANALYTICS_RESTATE_DAYS', 35),

    /* A center is at risk when its GMV drops by at least this percent against the previous period. */
    'at_risk_drop_percent' => (int) env('ANALYTICS_AT_RISK_DROP_PERCENT', 50),

    /* A center is at risk when it has had no counted purchase for this many days. */
    'at_risk_idle_days' => (int) env('ANALYTICS_AT_RISK_IDLE_DAYS', 14),

    /* Longest range, in days, a dashboard request may cover. */
    'max_range_days' => 366,
];
