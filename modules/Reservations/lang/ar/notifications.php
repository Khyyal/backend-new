<?php

return [
    'reservation' => [
        'created' => [
            'title' => 'حجز جديد',
            'body' => 'قام :client بحجز :service يوم :date الساعة :time.',
        ],
        'created_by_center' => [
            'title' => 'تم حجز موعد لك',
            'body' => 'قام :center بحجز :service لك يوم :date الساعة :time.',
        ],
        'confirmed' => [
            'title' => 'تم تأكيد الحجز',
            'body' => 'تم تأكيد حجزك لـ :service يوم :date الساعة :time.',
        ],
        'rescheduled' => [
            'title' => 'تم تغيير موعد الحجز',
            'body' => 'تم نقل حجزك لـ :service إلى يوم :date الساعة :time.',
        ],
        'completed' => [
            'title' => 'شكراً لزيارتك',
            'body' => 'اكتملت جلستك لـ :service في :center. قيّم تجربتك!',
        ],
        'no_show' => [
            'title' => 'حجز فائت',
            'body' => 'تم تسجيل غيابك عن :service يوم :date.',
        ],
        'cancelled' => [
            'title' => 'تم إلغاء الحجز',
            'body' => 'تم إلغاء حجزك لـ :service يوم :date.',
        ],
        'cancelled_by_center' => [
            'title' => 'تم إلغاء الحجز',
            'body' => 'قام :center بإلغاء حجزك لـ :service يوم :date.',
        ],
        'cancelled_by_client' => [
            'title' => 'تم إلغاء الحجز',
            'body' => 'قام :client بإلغاء :service يوم :date الساعة :time.',
        ],
    ],
    'package' => [
        'activated' => [
            'title' => 'تم تفعيل الباقة',
            'body' => 'باقتك لـ :service بعدد :sessions جلسات جاهزة للاستخدام.',
        ],
    ],
];
