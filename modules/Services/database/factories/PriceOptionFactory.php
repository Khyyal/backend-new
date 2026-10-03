<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;

class PriceOptionFactory extends Factory
{
    protected $model = PriceOption::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'price' => $this->faker->randomFloat(),
            'quantity' => $this->faker->randomNumber(),
            'unit' => $this->faker->randomElement(['option',]),

            'service_id' => Service::factory(),
        ];
    }
}
