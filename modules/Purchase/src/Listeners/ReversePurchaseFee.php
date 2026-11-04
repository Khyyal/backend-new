<?php

namespace Modules\Purchase\Listeners;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Events\Dispatcher;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Events\PaymentRefunded;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Models\PurchaseFee;

/**
 * The center no longer owes the platform fee once the purchase is cancelled
 * (which includes an expired or failed payment) or its payment is fully
 * refunded. Partial refunds keep the fee.
 */
class ReversePurchaseFee implements ShouldHandleEventsAfterCommit
{
    public function handleCancelled(PurchaseCancelled $event): void
    {
        $this->reverse($event->purchase->getKey());
    }

    public function handleRefunded(PaymentRefunded $event): void
    {
        if ($event->payment->status === PaymentStatus::Refunded) {
            $this->reverse($event->payment->purchase_id);
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

    private function reverse(int $purchaseId): void
    {
        PurchaseFee::query()
            ->where('purchase_id', $purchaseId)
            ->whereNull('reversed_at')
            ->update(['reversed_at' => now()]);
    }
}
