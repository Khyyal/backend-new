<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Super Admin
    |--------------------------------------------------------------------------
    |
    | Credentials of the first system user created by SuperAdminSeeder. The
    | seeder does nothing while the email or the password is empty.
    |
    */
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency Keys
    |--------------------------------------------------------------------------
    |
    | How long a stored Idempotency-Key response is replayed, and how long a
    | running request holds its key before a retry may take it over.
    |
    */
    'idempotency' => [
        'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
        'lock_seconds' => (int) env('IDEMPOTENCY_LOCK_SECONDS', 60),
    ],

];
