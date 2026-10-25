<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Visit;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'enter_type' => $this->faker->randomElement(VisitEnterType::cases())->value,
            'max_tickets_per_day' => $this->faker->numberBetween(10, 200),
        ];
    }
}
