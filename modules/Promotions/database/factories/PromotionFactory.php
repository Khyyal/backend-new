<?php

namespace Modules\Promotions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Models\Center;
use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Enums\PromotionStacking;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Models\Promotion;

/**
 * Defaults to a platform-wide automatic 10% item draft that started yesterday.
 *
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ['en' => ucfirst($name), 'ar' => $name],
            'trigger' => PromotionTrigger::Automatic->value,
            'scope' => PromotionScope::Item->value,
            'discount_type' => DiscountType::Percentage->value,
            'discount_value' => 1000,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'status' => PromotionStatus::Draft->value,
            'priority' => 0,
            'stacking' => PromotionStacking::Stackable->value,
            'funded_by' => FundedBy::Platform->value,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => PromotionStatus::Active->value]);
    }

    public function coupon(): static
    {
        return $this->state(['trigger' => PromotionTrigger::Coupon->value]);
    }

    public function forCenter(Center $center): static
    {
        return $this->state([
            'center_id' => $center->getKey(),
            'funded_by' => FundedBy::Center->value,
        ]);
    }

    public function percentage(int $basisPoints): static
    {
        return $this->state(['discount_type' => DiscountType::Percentage->value, 'discount_value' => $basisPoints]);
    }

    public function fixedAmount(int $halalas): static
    {
        return $this->state(['discount_type' => DiscountType::FixedAmount->value, 'discount_value' => $halalas]);
    }

    public function fixedPrice(int $halalas): static
    {
        return $this->state(['discount_type' => DiscountType::FixedPrice->value, 'discount_value' => $halalas]);
    }

    public function orderScope(): static
    {
        return $this->state(['scope' => PromotionScope::Order->value]);
    }

    public function exclusive(): static
    {
        return $this->state(['stacking' => PromotionStacking::Exclusive->value]);
    }
}
