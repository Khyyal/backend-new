<?php

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        $type = fake()->randomElement(DiscountType::cases());
        $value = match ($type) {
            DiscountType::Percentage => fake()->randomFloat(2, 1, 75),
            DiscountType::Fixed => fake()->randomFloat(2, 5, 500),
        };

        return [
            'owner_type' => 'center',
            'owner_id' => CenterFactory::new(),

            'name' => [
                'en' => fake()->words(3, true),
                'ar' => fake('ar_SA')->words(3, true),
            ],
            'description' => fake()->boolean(60) ? [
                'en' => fake()->sentence(),
                'ar' => fake('ar_SA')->sentence(),
            ] : null,

            'type' => $type,
            'value' => $value,

            'scope' => fake()->randomElement(DiscountScope::cases()),
            'application_method' => fake()->randomElement(ApplicationMethod::cases()),

            'minimum_amount' => fake()->optional(40)->randomFloat(2, 10, 500),
            'maximum_discount' => $type === DiscountType::Percentage ? fake()->optional(50)->randomFloat(2, 10, 200) : null,

            'usage_limit' => fake()->optional(40)->numberBetween(1, 999),
            'usage_limit_per_customer' => fake()->optional(30)->numberBetween(1, 5),

            'is_stackable' => fake()->boolean(80),

            'starts_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'ends_at' => fake()->optional(60)->dateTimeBetween('now', '+60 days'),

            'status' => PromotionStatus::Active,
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attr) => [
            'type' => DiscountType::Percentage,
            'value' => fake()->randomFloat(2, 1, 75),
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attr) => [
            'type' => DiscountType::Fixed,
            'value' => fake()->randomFloat(2, 5, 500),
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn () => [
            'application_method' => ApplicationMethod::Automatic,
        ]);
    }

    public function coupon(): static
    {
        return $this->state(fn () => [
            'application_method' => ApplicationMethod::Coupon,
        ]);
    }

    public function allItems(): static
    {
        return $this->state(fn () => [
            'scope' => DiscountScope::All,
        ]);
    }

    public function specificItems(): static
    {
        return $this->state(fn () => [
            'scope' => DiscountScope::SpecificItems,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => PromotionStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => PromotionStatus::InActive]);
    }
}
