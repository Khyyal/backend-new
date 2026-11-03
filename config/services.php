<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'voxa' => [
        'base_uri' => env('VOXA_BASE_URI', 'https://api.voxa.sa'),
        'client_id' => env('VOXA_CLIENT_ID'),
        'client_secret' => env('VOXA_CLIENT_SECRET'),
        'channel_id' => env('VOXA_CHANNEL_ID'),
        'timeout' => (int) env('VOXA_TIMEOUT', 10),
        'per_minute' => (int) env('VOXA_PER_MINUTE', 600),
    ],

];
