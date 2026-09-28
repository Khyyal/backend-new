<?php

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\Discount;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'discount_id' => Discount::factory(),

            'code' => Str::upper('SAVE'.Str::random(8)),

            'starts_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'ends_at' => fake()->optional(60)->dateTimeBetween('now', '+60 days'),

            'status' => PromotionStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => PromotionStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => PromotionStatus::InActive]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => PromotionStatus::Active,
            'ends_at' => now()->subDay(),
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn () => [
            'status' => PromotionStatus::Active,
            'starts_at' => now()->addDay(),
        ]);
    }
}
