<?php

namespace Modules\Promotions\Services;

use Illuminate\Support\Facades\DB;
use Modules\Promotions\Enums\RedemptionStatus;
use Modules\Promotions\Models\PromotionRedemption;

/**
 * Gives back the usage a purchase consumed, when the purchase is cancelled or
 * fully refunded. Idempotent: a redemption is released at most once, so queue
 * retries are harmless.
 */
class RedemptionReleaser
{
    public function releaseForPurchase(int $purchaseId, string $reason): int
    {
        return DB::transaction(function () use ($purchaseId, $reason): int {
            $released = 0;

            $redemptions = PromotionRedemption::query()
                ->where('purchase_id', $purchaseId)
                ->where('status', RedemptionStatus::Applied->value)
                ->orderBy('promotion_id')
                ->get();

            foreach ($redemptions as $redemption) {
                $claimed = DB::table('promotion_redemptions')
                    ->where('id', $redemption->getKey())
                    ->where('status', RedemptionStatus::Applied->value)
                    ->update([
                        'status' => RedemptionStatus::Released->value,
                        'released_at' => now(),
                        'release_reason' => $reason,
                        'updated_at' => now(),
                    ]);

                if ($claimed === 0) {
                    continue;
                }

                $released++;
                $this->decrement('promotions', $redemption->promotion_id, 'usage_count');
                $this->decrementClient('promotion_client_usages', 'promotion_id', $redemption->promotion_id, $redemption->client_id);

                if ($redemption->coupon_id !== null) {
                    $this->decrement('coupons', $redemption->coupon_id, 'used_count');
                    $this->decrementClient('coupon_client_usages', 'coupon_id', $redemption->coupon_id, $redemption->client_id);
                }
            }

            return $released;
        });
    }

    private function decrement(string $table, int $id, string $column): void
    {
        DB::table($table)->where('id', $id)->where($column, '>', 0)->decrement($column);
    }

    private function decrementClient(string $table, string $key, int $id, int $clientId): void
    {
        DB::table($table)->where($key, $id)->where('client_id', $clientId)->where('used_count', '>', 0)->decrement('used_count');
    }
}
