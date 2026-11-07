<?php

namespace Modules\Settlements\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Modules\Settlements\Actions\SyncPurchaseLedger;
use Modules\Purchase\Events\PaymentRefunded;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseConfirmed;

/**
 * Keeps the merchant center's ledger in step with every money change on a
 * purchase. Each event just re-syncs the purchase, so order and replays do
 * not matter.
 */
class SyncCenterLedger implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private SyncPurchaseLedger $sync) {}

    public function handlePurchase(PurchaseConfirmed|PurchaseCancelled $event): void
    {
        $this->sync->execute($event->purchase->getKey());
    }

    public function handlePayment(PaymentSucceeded|PaymentRefunded $event): void
    {
        $this->sync->execute($event->payment->purchase_id);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PurchaseConfirmed::class => 'handlePurchase',
            PurchaseCancelled::class => 'handlePurchase',
            PaymentSucceeded::class => 'handlePayment',
            PaymentRefunded::class => 'handlePayment',
        ];
    }
}
