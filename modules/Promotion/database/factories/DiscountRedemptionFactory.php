<?php

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotion\Models\Discount;
use Modules\Promotion\Models\DiscountRedemption;
use Modules\Purchase\Database\Factories\PurchaseFactory;

class DiscountRedemptionFactory extends Factory
{
    protected $model = DiscountRedemption::class;

    public function definition(): array
    {
        $usedBy = ClientFactory::new()->create();
        $discountable = PurchaseFactory::new()->create();

        return [
            'discount_id' => Discount::factory(),
            'coupon_id' => null,

            'used_by_type' => $usedBy->getMorphClass(),
            'used_by_id' => $usedBy->getKey(),

            'discountable_type' => $discountable->getMorphClass(),
            'discountable_id' => $discountable->getKey(),

            'discount_amount' => fake()->randomFloat(2, 1, 200),
            'redeemed_at' => now(),
        ];
    }
}
