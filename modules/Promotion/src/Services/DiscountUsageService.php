<?php

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Promotion\Events\CouponRedeemed;
use Modules\Promotion\Events\DiscountRedeemed;
use Modules\Promotion\Exceptions\DiscountLimitExceededException;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Discount;
use Modules\Promotion\Models\DiscountRedemption;
use Modules\Services\Models\Service;

class DiscountUsageService
{
    public function __construct(
        private readonly DiscountEligibilityService $eligibility,
    ) {}

    /**
     * @param  Model  $discountable  The record the discount was applied to (e.g. a purchase).
     * @param  iterable<Service|int>  $services  Services being purchased; required for `specific_items` discounts.
     */
    public function redeem(
        Discount $discount,
        Model $usedBy,
        Model $discountable,
        float|int $discountAmount,
        ?Coupon $coupon = null,
        float|int $subtotal = 0,
        iterable $services = [],
    ): DiscountRedemption {
        return DB::transaction(function () use ($discount, $usedBy, $discountable, $discountAmount, $coupon, $subtotal, $services) {
            $lockedDiscount = Discount::query()
                ->whereKey($discount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->eligibility->isEligible($lockedDiscount, $usedBy, $subtotal, $services)) {
                throw new DiscountLimitExceededException(
                    __('promotion::validation.discount_limit_exceeded')
                );
            }

            $redemption = DiscountRedemption::query()->create([
                'discount_id' => $lockedDiscount->id,
                'coupon_id' => $coupon?->id,
                'used_by_type' => $usedBy->getMorphClass(),
                'used_by_id' => $usedBy->getKey(),
                'discountable_type' => $discountable->getMorphClass(),
                'discountable_id' => $discountable->getKey(),
                'discount_amount' => round((float) $discountAmount, 2),
                'redeemed_at' => now(),
            ]);

            DB::afterCommit(function () use ($redemption, $coupon) {
                event(new DiscountRedeemed($redemption));

                if ($coupon !== null || $redemption->coupon_id !== null) {
                    event(new CouponRedeemed($redemption));
                }
            });

            return $redemption;
        });
    }
}
