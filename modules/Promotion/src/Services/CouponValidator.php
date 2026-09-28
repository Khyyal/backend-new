<?php

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Coupon;

class CouponValidator
{
    public function __construct(
        private readonly DiscountEligibilityService $eligibility,
    ) {}

    public function validate(
        string $code,
        ?Model $user = null,
        float|int $subtotal = 0,
        iterable $items = [],
    ): array {
        $normalized = Str::upper(trim($code));

        $coupon = Coupon::query()
            ->with('discount')
            ->where('code', $normalized)
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

        if (! $this->eligibility->isEligible($discount, $user, $subtotal, $items)) {
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
