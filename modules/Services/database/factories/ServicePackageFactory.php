<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\ServicePackage;

class ServicePackageFactory extends Factory
{
    protected $model = ServicePackage::class;

    public function definition(): array
    {
        return [
            'price_option_id' => PriceOption::factory(),
            'service_id' => fn (array $attributes) => PriceOption::query()->findOrFail($attributes['price_option_id'])->service_id,
            'count' => fake()->numberBetween(4, 20),
            'price' => fake()->numberBetween(100, 1000),
            'duration' => fake()->numberBetween(1, 6),
        ];
    }
}
