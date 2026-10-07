<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ResortDayPrice;

class ResortDayPriceFactory extends Factory
{
    protected $model = ResortDayPrice::class;

    public function definition(): array
    {
        return [
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'resort_id' => Resort::factory(),
        ];
    }
}
