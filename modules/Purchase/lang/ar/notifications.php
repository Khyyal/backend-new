<?php

return [
    'confirmed' => [
        'title' => 'تم تأكيد الشراء',
        'body' => 'تم تأكيد طلبك رقم #:id بمبلغ :amount ريال.',
    ],
    'cancelled' => [
        'title' => 'تم إلغاء الشراء',
        'body' => 'تم إلغاء طلبك رقم #:id.',
    ],
    'payment_failed' => [
        'title' => 'فشل الدفع',
        'body' => 'فشلت عملية دفع :amount ريال للطلب رقم #:id. يرجى المحاولة مرة أخرى.',
    ],
    'payment_refunded' => [
        'title' => 'تم استرداد المبلغ',
        'body' => 'تم استرداد :amount ريال للطلب رقم #:id.',
    ],
    'payout_paid' => [
        'title' => 'تم تحويل المستحقات',
        'body' => 'تم تحويل :amount ريال إلى حسابك البنكي (المرجع :reference).',
    ],
];
