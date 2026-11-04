<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionLifecycleService;
use Modules\Purchase\Events\PurchaseCancelled;

/**
 * A cancelled purchase voids the pending subscriptions it was opened for.
 */
class AbandonCancelledSubscription
{
    public function __construct(private SubscriptionLifecycleService $lifecycle) {}

    public function handle(PurchaseCancelled $event): void
    {
        foreach ($event->purchase->items()->get() as $item) {
            if ($item->purchasable instanceof Subscription) {
                $this->lifecycle->abandon($item->purchasable);
            }
        }
    }
}
