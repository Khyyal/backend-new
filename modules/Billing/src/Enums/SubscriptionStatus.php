<?php

namespace Modules\Billing\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Trialing = 'trialing';
}
