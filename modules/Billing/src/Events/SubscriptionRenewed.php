<?php

namespace Modules\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Models\Subscription;

class SubscriptionRenewed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Subscription $subscription,
    ) {}
}
