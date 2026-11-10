<?php

return [

    'ttl' => 300,

    'dev' => [
        'enabled' => env('OTP_DEV_ENABLED', false),

        'code' => env('OTP_DEV_CODE', '1234'),

        'phones' => array_filter(
            array_map(
                'trim',
                explode(',', env('OTP_DEV_PHONES', ''))
            )
        ),
    ],

];
