<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transactional WhatsApp templates
    |--------------------------------------------------------------------------
    |
    | Voxa template names (approved in Meta) sent to a center's primary user.
    | The purchase message goes out when a center receives a purchase after
    | `center_purchase_quiet_days` without one (its first purchase included).
    |
    */
    'whatsapp' => [
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),
        'center_welcome_template' => env('WHATSAPP_CENTER_WELCOME_TEMPLATE', 'welcom'),
        'center_purchase_template' => env('WHATSAPP_CENTER_PURCHASE_TEMPLATE', 'new_purchase'),
        'center_purchase_quiet_days' => (int) env('WHATSAPP_CENTER_PURCHASE_QUIET_DAYS', 21),
    ],
];
