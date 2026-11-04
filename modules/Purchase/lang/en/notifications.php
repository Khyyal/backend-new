<?php

return [
    'confirmed' => [
        'title' => 'Purchase confirmed',
        'body' => 'Your order #:id of :amount SAR is confirmed.',
    ],
    'cancelled' => [
        'title' => 'Purchase cancelled',
        'body' => 'Your order #:id was cancelled.',
    ],
    'payment_failed' => [
        'title' => 'Payment failed',
        'body' => 'The payment of :amount SAR for order #:id failed. Please try again.',
    ],
    'payment_refunded' => [
        'title' => 'Refund issued',
        'body' => ':amount SAR was refunded for order #:id.',
    ],
    'payout_paid' => [
        'title' => 'Payout transferred',
        'body' => 'A payout of :amount SAR was transferred to your bank account (reference :reference).',
    ],
];
