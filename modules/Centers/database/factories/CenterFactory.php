<?php

namespace Modules\Centers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Models\Center;
use Modules\Support\Models\City;

class CenterFactory extends Factory
{
    protected $model = Center::class;

    public function definition(): array
    {
        return [
            'city_id' => City::factory(),

            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),

            'description' => fake()->optional()->paragraph(),

            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),

            'address' => fake()->address(),

            'points' => 0,

            'status' => fake()->randomElement([
                'visible',
                'invisible',
            ]),
        ];
    }
}
