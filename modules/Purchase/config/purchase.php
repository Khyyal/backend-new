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
            'enabled' => env('TAMARA_ENABLED', false),
            'environment' => env('TAMARA_ENVIRONMENT', 'sandbox'),
            'api_token' => env('TAMARA_API_TOKEN', ''),
            'notification_token' => env('TAMARA_NOTIFICATION_TOKEN', ''),
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
            'enabled' => env('MOYASAR_ENABLED', false),
            'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY', ''),
            'secret_key' => env('MOYASAR_SECRET_KEY', ''),
            'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET', ''),
            'currency' => env('MOYASAR_CURRENCY', 'SAR'),
        ],

    ],

];
