<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Models\Membership;

class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'visit_service_id' => null,
            'max_visits_per_day' => null,
            'duration' => fake()->numberBetween(1, 12),
            'color' => fake()->hexColor(),
        ];
    }
}
