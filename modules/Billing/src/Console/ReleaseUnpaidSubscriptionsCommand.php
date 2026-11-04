<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PurchaseStateService;

/**
 * Cancels online subscription checkouts left unpaid past the hold window, so
 * their pending subscriptions are voided and the center can check out again.
 * Checkouts with a payment in progress are kept. Pending payments are left
 * open so a late gateway success can still be recorded, and
 * RefundLateSubscriptionPayment returns it.
 */
class ReleaseUnpaidSubscriptionsCommand extends Command
{
    protected $signature = 'billing:release-unpaid';

    protected $description = 'Cancel online subscription checkouts left unpaid past the hold window';

    public function handle(PurchaseStateService $purchaseState): int
    {
        $holdMinutes = (int) config('billing.unpaid_hold_minutes');
        $released = 0;

        Purchase::query()
            ->where('status', PurchaseStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes($holdMinutes))
            ->whereHas('items', fn (Builder $query) => $query->where('purchasable_type', 'subscription'))
            ->whereHas('payments', fn (Builder $query) => $query->where('method', PaymentMethod::Online))
            ->whereDoesntHave('payments', fn (Builder $query) => $query->whereIn('status', [
                PaymentStatus::Processing,
                PaymentStatus::Succeeded,
                PaymentStatus::Refunded,
            ]))
            ->chunkById(100, function ($purchases) use ($purchaseState, &$released): void {
                foreach ($purchases as $purchase) {
                    if ($this->release($purchase, $purchaseState)) {
                        $released++;
                    }
                }
            });

        $this->info("Released {$released} unpaid subscription checkout(s).");

        return self::SUCCESS;
    }

    /**
     * Re-checks under lock: the purchase may have been paid since it was listed.
     */
    private function release(Purchase $purchase, PurchaseStateService $purchaseState): bool
    {
        return DB::transaction(function () use ($purchase, $purchaseState): bool {
            $locked = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->first();

            $isPaying = $locked?->payments()
                ->whereIn('status', [PaymentStatus::Processing, PaymentStatus::Succeeded, PaymentStatus::Refunded])
                ->exists();

            if ($locked === null || $locked->status !== PurchaseStatus::Pending || $isPaying) {
                return false;
            }

            $purchaseState->cancel($locked);

            return true;
        });
    }
}
