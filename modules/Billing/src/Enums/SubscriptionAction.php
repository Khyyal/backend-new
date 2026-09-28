<?php

namespace Modules\Billing\Enums;

enum SubscriptionAction: string
{
    case Subscribe = 'subscribe';
    case Cancel = 'cancel';
    case Renew = 'renew';
    case Expire = 'expire';
}
