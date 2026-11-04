<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionLifecycleService;
use Modules\Purchase\Events\PurchaseConfirmed;

/**
 * A confirmed purchase activates the pending subscriptions it paid for.
 */
class ActivatePaidSubscription
{
    public function __construct(private SubscriptionLifecycleService $lifecycle) {}

    public function handle(PurchaseConfirmed $event): void
    {
        foreach ($event->purchase->items()->get() as $item) {
            if ($item->purchasable instanceof Subscription) {
                $this->lifecycle->activate($item->purchasable);
            }
        }
    }
}
