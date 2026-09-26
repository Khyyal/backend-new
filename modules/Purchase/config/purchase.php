<?php

return [

    'default_gateway' => env('PURCHASE_GATEWAY', 'fake'),

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
