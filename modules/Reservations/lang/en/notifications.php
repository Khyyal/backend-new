<?php

return [
    'reservation' => [
        'created' => [
            'title' => 'New reservation',
            'body' => ':client booked :service on :date at :time.',
        ],
        'created_by_center' => [
            'title' => 'Reservation booked for you',
            'body' => ':center booked :service for you on :date at :time.',
        ],
        'confirmed' => [
            'title' => 'Reservation confirmed',
            'body' => 'Your :service reservation on :date at :time is confirmed.',
        ],
        'rescheduled' => [
            'title' => 'Reservation rescheduled',
            'body' => 'Your :service reservation was moved to :date at :time.',
        ],
        'completed' => [
            'title' => 'Thanks for visiting',
            'body' => 'Your :service session at :center is complete. Rate your experience!',
        ],
        'no_show' => [
            'title' => 'Missed reservation',
            'body' => 'You were marked absent for :service on :date.',
        ],
        'cancelled' => [
            'title' => 'Reservation cancelled',
            'body' => 'Your :service reservation on :date was cancelled.',
        ],
        'cancelled_by_center' => [
            'title' => 'Reservation cancelled',
            'body' => ':center cancelled your :service reservation on :date.',
        ],
        'cancelled_by_client' => [
            'title' => 'Reservation cancelled',
            'body' => ':client cancelled :service on :date at :time.',
        ],
        'reminder' => [
            'title' => 'Upcoming reservation',
            'body' => 'Reminder: your :service reservation at :center is on :date at :time.',
        ],
    ],
    'package' => [
        'activated' => [
            'title' => 'Package activated',
            'body' => 'Your :service package with :sessions sessions is ready to use.',
        ],
        'expiring' => [
            'title' => 'Package expiring soon',
            'body' => 'Your :service package expires on :date. Book your remaining sessions before then.',
        ],
        'expired' => [
            'title' => 'Package expired',
            'body' => 'Your :service package has expired.',
        ],
        'exhausted' => [
            'title' => 'Package sessions used up',
            'body' => "You've booked all :sessions sessions of your :service package.",
        ],
    ],
    'membership' => [
        'expiring' => [
            'title' => 'Membership expiring soon',
            'body' => 'Your :membership membership expires on :date.',
        ],
        'expired' => [
            'title' => 'Membership expired',
            'body' => 'Your :membership membership has expired.',
        ],
    ],
];
