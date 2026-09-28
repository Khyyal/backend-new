<?php

namespace Modules\Billing\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Trialing = 'trialing';
}
