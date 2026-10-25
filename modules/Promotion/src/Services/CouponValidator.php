<?php

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Centers\Models\Center;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Discount;
use Modules\Services\Models\Service;

class CouponValidator
{
    public function __construct(
        private readonly DiscountEligibilityService $eligibility,
    ) {}

    /**
     * Validate a coupon code for a purchase at the given center. Coupons of
     * other centers are reported as not found.
     *
     * @param  iterable<Service|int>  $services
     * @return array{valid: bool, reason?: string, code?: int, coupon?: Coupon, discount?: Discount}
     */
    public function validate(
        string $code,
        Center $center,
        ?Model $user = null,
        float|int $subtotal = 0,
        iterable $services = [],
    ): array {
        $normalized = Str::upper(trim($code));

        $coupon = Coupon::query()
            ->with('discount.services')
            ->where('code', $normalized)
            ->whereRelation('discount', 'center_id', $center->getKey())
            ->first();

        if ($coupon === null) {
            return [
                'valid' => false,
                'reason' => 'coupon_not_found',
                'code' => 404,
            ];
        }

        if ($coupon->status !== PromotionStatus::Active) {
            return [
                'valid' => false,
                'reason' => 'coupon_inactive',
                'code' => 422,
                'coupon' => $coupon,
            ];
        }

        $now = now();

        if ($coupon->starts_at->gt($now)) {
            return [
                'valid' => false,
                'reason' => 'coupon_expired',
                'code' => 422,
                'coupon' => $coupon,
            ];
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->lt($now)) {
            return [
                'valid' => false,
                'reason' => 'coupon_expired',
                'code' => 422,
                'coupon' => $coupon,
            ];
        }

        $discount = $coupon->discount;

        if (! $this->eligibility->isEligible($discount, $user, $subtotal, $services)) {
            return [
                'valid' => false,
                'reason' => 'discount_ineligible',
                'code' => 422,
                'coupon' => $coupon,
                'discount' => $discount,
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
        ];
    }
}
