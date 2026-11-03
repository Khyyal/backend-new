<?php

namespace Modules\Promotions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'promotion_id' => PromotionFactory::new()->coupon()->active(),
            'code' => strtoupper(Str::random(8)),
            'status' => CouponStatus::Active->value,
            'is_assigned_only' => false,
        ];
    }

    public function forPromotion(Promotion $promotion): static
    {
        return $this->state(['promotion_id' => $promotion->getKey()]);
    }

    public function withCode(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function disabled(): static
    {
        return $this->state(['status' => CouponStatus::Disabled->value]);
    }

    public function assignedOnly(): static
    {
        return $this->state(['is_assigned_only' => true]);
    }
}
