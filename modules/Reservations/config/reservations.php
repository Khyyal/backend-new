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

    /*
    |--------------------------------------------------------------------------
    | Reminder
    |--------------------------------------------------------------------------
    |
    | Hours before a confirmed reservation starts that
    | `reservations:send-reminders` notifies its client.
    |
    */
    'reminder_hours_before' => (int) env('RESERVATIONS_REMINDER_HOURS_BEFORE', 4),

    /*
    |--------------------------------------------------------------------------
    | Expiry reminder
    |--------------------------------------------------------------------------
    |
    | Days before an active package or membership expires that
    | `reservations:send-expiry-reminders` notifies its client.
    |
    */
    'expiry_reminder_days_before' => (int) env('RESERVATIONS_EXPIRY_REMINDER_DAYS_BEFORE', 3),
];
