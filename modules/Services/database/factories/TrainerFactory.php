<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Models\Trainer;

class TrainerFactory extends Factory
{
    protected $model = Trainer::class;

    public function definition(): array
    {
        return [
            'center_id' => CenterFactory::new(),
            'name' => $this->faker->name(),
            'max_tickets_per_hour' => $this->faker->numberBetween(1, 10),
        ];
    }
}
