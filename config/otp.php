<?php

return [

    'ttl' => 300,

    'dev' => [
        'enabled' => env('OTP_DEV_ENABLED', false),

        'code' => env('OTP_DEV_CODE', '123456'),

        'phones' => array_filter(
            array_map(
                'trim',
                explode(',', env('OTP_DEV_PHONES', ''))
            )
        ),
    ],

];
