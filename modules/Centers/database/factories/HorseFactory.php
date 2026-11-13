<?php

namespace Modules\Centers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Models\Horse;
use Modules\Clients\Database\Factories\ClientFactory;

/**
 * @extends Factory<Horse>
 */
class HorseFactory extends Factory
{
    protected $model = Horse::class;

    public function definition(): array
    {
        return [
            'center_id' => CenterFactory::new(),
            'client_id' => null,
            'name' => fake()->firstName(),
            'room' => fake()->numberBetween(1, 50),
        ];
    }

    public function forClient(): static
    {
        return $this->state(fn () => ['client_id' => ClientFactory::new()]);
    }
}
