<?php

namespace Modules\Promotions\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Promotions\Contracts\PromotionRedeemer;
use Modules\Promotions\Data\PriceAdjustment;
use Modules\Promotions\Enums\CouponCheck;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Exceptions\CouponRejectedException;
use Modules\Promotions\Exceptions\PromotionUnavailableException;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Models\PromotionRedemption;

/**
 * Authoritative usage accounting, run inside the purchase transaction.
 *
 * Limits are enforced with single conditional UPDATE statements ("increment
 * only while under the limit") rather than read-then-write, so concurrent
 * checkouts cannot both take the last use: zero affected rows means the limit
 * was already reached. Status and date window are re-checked in the same
 * statement, so a promotion paused mid-checkout fails closed. Locks are taken
 * promotions → coupons → per-client rows, each in id order, to avoid deadlocks.
 */
class RedemptionService implements PromotionRedeemer
{
    public function __construct(
        private readonly UsageLimitGuard $limits,
    ) {}

    public function redeem(int $purchaseId, int $clientId, array $adjustments): void
    {
        $grouped = $this->groupByPromotion($adjustments);

        if ($grouped === []) {
            return;
        }

        ksort($grouped);
        $now = now();

        $promotions = Promotion::query()->whereKey(array_keys($grouped))->orderBy('id')->get()->keyBy('id');
        $coupons = Coupon::query()
            ->whereKey(collect($grouped)->pluck('couponId')->filter()->all())
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        foreach ($grouped as $promotionId => $entry) {
            $promotion = $promotions->get($promotionId) ?? throw PromotionUnavailableException::forPromotion($promotionId);
            $this->consumePromotion($promotion, $entry['couponId'] !== null, $now);
        }

        foreach ($grouped as $entry) {
            if ($entry['couponId'] !== null) {
                $this->consumeCoupon($coupons->get($entry['couponId']) ?? throw new CouponRejectedException(CouponCheck::Invalid), $now);
            }
        }

        foreach ($grouped as $promotionId => $entry) {
            $promotion = $promotions->get($promotionId);
            $coupon = $entry['couponId'] !== null ? $coupons->get($entry['couponId']) : null;
            $viaCoupon = $coupon !== null;

            if ($viaCoupon && $coupon->per_client_usage_limit !== null) {
                $this->consumeClientSlot('coupon_client_usages', 'coupon_id', $coupon->getKey(), $clientId, $coupon->per_client_usage_limit, true);
            } elseif ($promotion->per_client_usage_limit !== null) {
                $this->consumeClientSlot('promotion_client_usages', 'promotion_id', $promotionId, $clientId, $promotion->per_client_usage_limit, $viaCoupon);
            }

            if ($promotion->first_booking_only) {
                $this->assertFirstBooking($clientId, $purchaseId, $viaCoupon);
            }
        }

        foreach ($grouped as $promotionId => $entry) {
            PromotionRedemption::create([
                'promotion_id' => $promotionId,
                'coupon_id' => $entry['couponId'],
                'purchase_id' => $purchaseId,
                'client_id' => $clientId,
                'discount_amount' => $entry['amount'],
                'funded_by' => $entry['fundedBy'],
                'center_share_amount' => $entry['centerShare'],
            ]);
        }
    }

    /**
     * @param  list<PriceAdjustment>  $adjustments
     * @return array<int, array{amount: int, centerShare: int, fundedBy: string, couponId: int|null}>
     */
    private function groupByPromotion(array $adjustments): array
    {
        $grouped = [];

        foreach ($adjustments as $adjustment) {
            $entry = $grouped[$adjustment->promotionId] ?? [
                'amount' => 0,
                'centerShare' => 0,
                'fundedBy' => $adjustment->fundedBy->value,
                'couponId' => $adjustment->couponId,
            ];

            $entry['amount'] += $adjustment->amount;
            $entry['centerShare'] += $adjustment->centerShareAmount;
            $grouped[$adjustment->promotionId] = $entry;
        }

        return $grouped;
    }

    private function consumePromotion(Promotion $promotion, bool $viaCoupon, CarbonInterface $now): void
    {
        $affected = DB::table('promotions')
            ->where('id', $promotion->getKey())
            ->whereNull('deleted_at')
            ->where('status', PromotionStatus::Active->value)
            ->where('starts_at', '<=', $now)
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->where(fn (Builder $query) => $query->whereNull('total_usage_limit')->orWhereColumn('usage_count', '<', 'total_usage_limit'))
            ->increment('usage_count');

        if ($affected === 0) {
            $this->fail($viaCoupon, $this->limits->totalLimitReached($promotion) ? CouponCheck::Exhausted : CouponCheck::Expired, $promotion->getKey());
        }
    }

    private function consumeCoupon(Coupon $coupon, CarbonInterface $now): void
    {
        $affected = DB::table('coupons')
            ->where('id', $coupon->getKey())
            ->where('status', CouponStatus::Active->value)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->where(fn (Builder $query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->increment('used_count');

        if ($affected === 0) {
            $fresh = DB::table('coupons')->where('id', $coupon->getKey())->first();
            $exhausted = $fresh !== null && $fresh->usage_limit !== null && $fresh->used_count >= $fresh->usage_limit;

            throw new CouponRejectedException($exhausted ? CouponCheck::Exhausted : CouponCheck::Expired);
        }
    }

    private function consumeClientSlot(string $table, string $column, int $id, int $clientId, int $limit, bool $viaCoupon): void
    {
        DB::table($table)->insertOrIgnore([$column => $id, 'client_id' => $clientId, 'used_count' => 0]);

        $affected = DB::table($table)
            ->where($column, $id)
            ->where('client_id', $clientId)
            ->where('used_count', '<', $limit)
            ->increment('used_count');

        if ($affected === 0) {
            $this->fail($viaCoupon, CouponCheck::ClientLimitReached, $id);
        }
    }

    private function assertFirstBooking(int $clientId, int $purchaseId, bool $viaCoupon): void
    {
        // Serialise concurrent first bookings of the same client on the client row.
        DB::table('clients')->where('id', $clientId)->lockForUpdate()->first();

        if ($this->limits->hasPriorBookings($clientId, $purchaseId)) {
            $this->fail($viaCoupon, CouponCheck::NotEligible, $purchaseId);
        }
    }

    private function fail(bool $viaCoupon, CouponCheck $check, int $id): never
    {
        throw $viaCoupon ? new CouponRejectedException($check) : PromotionUnavailableException::forPromotion($id);
    }
}
