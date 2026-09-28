<?php

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Promotion\Models\Discountable;

class DiscountableFactory extends Factory
{

    protected $model = Discountable::class;
    public function definition(): array
    {
        return [
            'discount_id' => $this->faker->randomDigitNotNull(),
            'discountable_type' => $this->faker->word(),
            'discountable_id' => $this->faker->randomDigitNotNull(),
        ];
    }
}
