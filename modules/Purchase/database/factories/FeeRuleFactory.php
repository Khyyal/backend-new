<?php

namespace Modules\Purchase\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Models\FeeRule;

/**
 * @extends Factory<FeeRule>
 */
class FeeRuleFactory extends Factory
{
    protected $model = FeeRule::class;

    public function definition(): array
    {
        return [
            'center_id' => null,
            'channel' => null,
            'percentage_bps' => fake()->numberBetween(100, 1500),
            'fixed_amount' => 0,
            'min_amount' => null,
            'max_amount' => null,
            'is_active' => true,
        ];
    }

    public function forCenter(Center $center): static
    {
        return $this->state(['center_id' => $center->getKey()]);
    }

    public function forChannel(PurchaseChannel $channel): static
    {
        return $this->state(['channel' => $channel]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
