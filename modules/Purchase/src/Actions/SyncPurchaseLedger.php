<?php

namespace Modules\Purchase\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\CenterLedgerEntryType;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\CenterLedgerEntry;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseAdjustment;

/**
 * Brings a center purchase's ledger entries in line with its current state
 * by appending the difference. Running it again, or for events handled out
 * of order, changes nothing.
 *
 * - Online payments are credited, available after the hold period; refunds
 *   on them are debited. Cash and manual payments stay with the center.
 * - While the purchase is confirmed or completed and its fee not reversed,
 *   the platform fee is debited and the platform-funded discount credited;
 *   otherwise both are taken back.
 */
class SyncPurchaseLedger
{
    public function execute(int $purchaseId): void
    {
        DB::transaction(function () use ($purchaseId): void {
            $purchase = Purchase::query()->whereKey($purchaseId)->lockForUpdate()->first();

            if ($purchase === null || $purchase->merchant_type !== (new Center)->getMorphClass()) {
                return;
            }

            $purchase->load(['fee', 'adjustments', 'payments', 'ledgerEntries']);

            foreach ($purchase->payments as $payment) {
                $this->syncPayment($purchase, $payment);
            }

            $isSettled = in_array($purchase->status, [PurchaseStatus::Confirmed, PurchaseStatus::Completed], true)
                && $purchase->fee !== null
                && $purchase->fee->reversed_at === null;

            $this->syncNet(
                $purchase,
                CenterLedgerEntryType::feeTypes(),
                target: $isSettled ? -$purchase->fee->amount : 0,
                increase: CenterLedgerEntryType::FeeReversal,
                decrease: CenterLedgerEntryType::PlatformFee,
                keyPrefix: 'fee',
            );

            $subsidy = $purchase->adjustments->sum(
                fn (PurchaseAdjustment $adjustment): int => $adjustment->amount - $adjustment->center_share_amount
            );

            $this->syncNet(
                $purchase,
                CenterLedgerEntryType::subsidyTypes(),
                target: $isSettled ? $subsidy : 0,
                increase: CenterLedgerEntryType::PlatformSubsidy,
                decrease: CenterLedgerEntryType::SubsidyReversal,
                keyPrefix: 'subsidy',
            );
        });
    }

    private function syncPayment(Purchase $purchase, Payment $payment): void
    {
        if ($payment->method !== PaymentMethod::Online
            || ! in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::Refunded], true)) {
            return;
        }

        $this->post($purchase, CenterLedgerEntryType::Payment, $payment->amount, $this->releaseDate($payment->paid_at), "payment:{$payment->id}", $payment);

        $refunded = -$purchase->ledgerEntries
            ->where('payment_id', $payment->id)
            ->where('type', CenterLedgerEntryType::Refund)
            ->sum('amount');

        if ($payment->refunded_amount > $refunded) {
            $this->post($purchase, CenterLedgerEntryType::Refund, $refunded - $payment->refunded_amount, now(), "refund:{$payment->id}:{$payment->refunded_amount}", $payment);
        }
    }

    /**
     * Appends one entry moving the net of `$types` to `$target`.
     *
     * @param  array<CenterLedgerEntryType>  $types
     */
    private function syncNet(
        Purchase $purchase,
        array $types,
        int $target,
        CenterLedgerEntryType $increase,
        CenterLedgerEntryType $decrease,
        string $keyPrefix,
    ): void {
        $entries = $purchase->ledgerEntries->whereIn('type', $types);
        $difference = $target - $entries->sum('amount');

        if ($difference === 0) {
            return;
        }

        $type = $difference > 0 ? $increase : $decrease;
        $availableAt = $type === CenterLedgerEntryType::PlatformSubsidy ? $this->releaseDate(now()) : now();
        $sequence = $entries->count() + 1;

        $this->post($purchase, $type, $difference, $availableAt, "{$keyPrefix}:{$purchase->id}:{$sequence}");
    }

    private function post(
        Purchase $purchase,
        CenterLedgerEntryType $type,
        int $amount,
        Carbon $availableAt,
        string $key,
        ?Payment $payment = null,
    ): void {
        if ($amount === 0) {
            return;
        }

        $entry = CenterLedgerEntry::query()->createOrFirst(['idempotency_key' => $key], [
            'center_id' => $purchase->merchant_id,
            'purchase_id' => $purchase->id,
            'payment_id' => $payment?->id,
            'type' => $type,
            'amount' => $amount,
            'available_at' => $availableAt,
        ]);

        if ($entry->wasRecentlyCreated) {
            $purchase->ledgerEntries->push($entry);
        }
    }

    private function releaseDate(?Carbon $from): Carbon
    {
        return ($from ?? now())->copy()->addDays((int) config('purchase.payouts.hold_days'));
    }
}
