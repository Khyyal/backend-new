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

class DiscountUsageService
{
    public function __construct(
        private readonly DiscountEligibilityService $eligibility,
    ) {}

    public function redeem(
        Discount $discount,
        Model $usedBy,
        Model $discountable,
        float|int $discountAmount,
        ?Coupon $coupon = null,
        float|int $subtotal = 0,
    ): DiscountRedemption {
        return DB::transaction(function () use ($discount, $usedBy, $discountable, $discountAmount, $coupon, $subtotal) {
            $lockedDiscount = Discount::query()
                ->whereKey($discount->id)
                ->lockForUpdate()
                ->firstOrFail();

            $items = [];
            if (method_exists($discountable, 'items') && $discountable->relationLoaded('items')) {
                $items = $discountable->items->all();
            }

            if (! $this->eligibility->isEligible($lockedDiscount, $usedBy, $subtotal, $items)) {
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
