<?php

return [

    'default_gateway' => env('PURCHASE_GATEWAY', 'fake'),

    'payouts' => [
        /*
         * Days after an online payment succeeds before the center can be
         * paid for it, so refunds and chargebacks can land first.
         */
        'hold_days' => (int) env('PAYOUT_HOLD_DAYS', 7),
    ],

    /*
     * What each gateway charges the platform per succeeded payment, used when
     * the provider does not report the fee itself. Basis points + halalas.
     */
    'provider_fees' => [
        'moyasar' => [
            'percentage_bps' => (int) env('MOYASAR_FEE_BPS', 0),
            'fixed_amount' => (int) env('MOYASAR_FEE_FIXED', 0),
        ],
        'tamara' => [
            'percentage_bps' => (int) env('TAMARA_FEE_BPS', 0),
            'fixed_amount' => (int) env('TAMARA_FEE_FIXED', 0),
        ],
    ],

    'providers' => [

        'tamara' => [
            'enabled' => env('TAMARA_ENABLED', true),
            'environment' => env('TAMARA_ENVIRONMENT', 'sandbox'),
            'api_token' => env('TAMARA_API_TOKEN', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhY2NvdW50SWQiOiI3MGM4NzI0NC1kMGY3LTRkOWMtOGZiNC0zNjhiZTRmZWFiOTgiLCJ0eXBlIjoibWVyY2hhbnQiLCJzYWx0IjoiNjE4YzNkNDIxODg0MjI4NGJjNmU4ODA5MmNlZmJkOTIiLCJyb2xlcyI6WyJST0xFX01FUkNIQU5UIl0sImlhdCI6MTczNjI2MjMyNiwiaXNzIjoiVGFtYXJhIn0.wyI-NoA5YrVUmG3YS7M1PHHO2wm0Lw17RFleQeJx4H6eojWAwEbgtYhx7OWd7zuyYQWrEpY1flNOiiXDZdLa6xH8stHRPpIvXFyqWHCct2P4qjCwW5J1_O4Ojc5rLOPRy1GxolixQbrkL9XzHGH0EJe0lUcVyN8sh8qkIE5ysZMqtPphuCJvq0JRIS2ohFaFxCsGOd_1GnQQL0oq33Melpn2iUeI_TsgXxb8lOnDZgY7wVY3fjzZsrXvl4vG4FLsoxQqVFhW7N-flg-ydt6wNkweVhhI0R43pIx0BqVzRfxqn429yLv2xP2BGNzOe687HbZUSRMFu_q2KbBVGIzSfQ'),
            'notification_token' => env('TAMARA_NOTIFICATION_TOKEN', '0427fcc9-e402-4b70-a997-2c34b100d9d5'),
            'country' => env('TAMARA_COUNTRY', 'SA'),
            'currency' => env('TAMARA_CURRENCY', 'SAR'),
            'locale' => env('TAMARA_LOCALE', 'en_US'),
            'urls' => [
                'success' => env('TAMARA_SUCCESS_URL', ''),
                'failure' => env('TAMARA_FAILURE_URL', ''),
                'cancel' => env('TAMARA_CANCEL_URL', ''),
                'notification' => env('TAMARA_NOTIFICATION_URL', ''),
            ],
            'default_payment_type' => env('TAMARA_DEFAULT_PAYMENT_TYPE', 'PAY_BY_INSTALMENTS'),
        ],

        'moyasar' => [
            'enabled' => env('MOYASAR_ENABLED', true),
            'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY', 'e4c5bc91-7411-4be0-8c65-fb672451e162'),
            'secret_key' => env('MOYASAR_SECRET_KEY', 'pk_test_eTcSEVySJLpbfjEngbufkkfU9DcPjMJjUc7QatSz'),
            'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET', ''),
            'currency' => env('MOYASAR_CURRENCY', 'SAR'),
        ],

    ],

];
