<?php

namespace Modules\Analytics\Enums;

/**
 * The tabs of the admin analytics page; each has its own endpoint.
 */
enum AnalyticsSection: string
{
    case Overview = 'overview';
    case Revenue = 'revenue';
    case Bookings = 'bookings';
    case Payments = 'payments';
    case Clients = 'clients';
    case Centers = 'centers';
    case Promotions = 'promotions';
    case Subscriptions = 'subscriptions';
}
