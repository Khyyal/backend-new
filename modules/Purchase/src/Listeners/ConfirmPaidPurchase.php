<?php

namespace Modules\Purchase\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PurchaseStateService;

/**
 * Confirms a pending purchase once its succeeded payments cover the total.
 * A purchase already confirmed (or cancelled) is left as it is, so replayed
 * webhooks and partial payments are harmless.
 */
class ConfirmPaidPurchase
{
    public function __construct(private PurchaseStateService $purchaseState) {}

    public function handle(PaymentSucceeded $event): void
    {
        DB::transaction(function () use ($event): void {
            $purchase = Purchase::query()
                ->whereKey($event->payment->purchase_id)
                ->lockForUpdate()
                ->first();

            if ($purchase === null || $purchase->status !== PurchaseStatus::Pending) {
                return;
            }

            $paid = (float) $purchase->payments()
                ->where('status', PaymentStatus::Succeeded)
                ->sum('amount');

            if (round($paid, 2) < round((float) $purchase->total_amount, 2)) {
                return;
            }

            $this->purchaseState->confirm($purchase);
        });
    }
}
