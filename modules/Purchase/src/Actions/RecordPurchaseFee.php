<?php

namespace Modules\Purchase\Actions;

use Modules\Centers\Models\Center;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseAdjustment;
use Modules\Purchase\Models\PurchaseFee;
use Modules\Purchase\Services\FeeRuleResolver;

/**
 * Snapshots the platform fee a center owes on a purchase. The fee is taken
 * on what the center earns: the total paid plus the part of the discounts
 * the platform funded. Purchases from other merchants carry no fee.
 */
class RecordPurchaseFee
{
    public function __construct(
        private FeeRuleResolver $resolver,
    ) {}

    public function execute(Purchase $purchase, Center $center): PurchaseFee
    {
        $rule = $this->resolver->resolve($center, $purchase->channel);
        $baseAmount = $purchase->total_amount + $purchase->adjustments->sum(
            fn (PurchaseAdjustment $adjustment): int => $adjustment->amount - $adjustment->center_share_amount
        );

        return $purchase->fee()->create([
            'fee_rule_id' => $rule?->getKey(),
            'channel' => $purchase->channel,
            'base_amount' => $baseAmount,
            'percentage_bps' => $rule->percentage_bps ?? 0,
            'fixed_amount' => $rule->fixed_amount ?? 0,
            'min_amount' => $rule?->min_amount,
            'max_amount' => $rule?->max_amount,
            'amount' => $rule?->calculate($baseAmount) ?? 0,
        ]);
    }
}
