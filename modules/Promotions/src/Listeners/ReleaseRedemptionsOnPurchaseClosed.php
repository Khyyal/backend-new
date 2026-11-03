<?php

namespace Modules\Promotions\Listeners;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Modules\Promotions\Services\RedemptionReleaser;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Events\PaymentRefunded;
use Modules\Purchase\Events\PurchaseCancelled;

/**
 * Gives back promotion and coupon usage when a purchase is cancelled (which
 * includes an expired or failed payment) or its payment is fully refunded.
 */
class ReleaseRedemptionsOnPurchaseClosed implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public function __construct(
        private readonly RedemptionReleaser $releaser,
    ) {}

    public function handleCancelled(PurchaseCancelled $event): void
    {
        $this->releaser->releaseForPurchase($event->purchase->getKey(), 'purchase_cancelled');
    }

    public function handleRefunded(PaymentRefunded $event): void
    {
        if ($event->payment->status === PaymentStatus::Refunded) {
            $this->releaser->releaseForPurchase($event->payment->purchase_id, 'payment_refunded');
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PurchaseCancelled::class => 'handleCancelled',
            PaymentRefunded::class => 'handleRefunded',
        ];
    }
}
