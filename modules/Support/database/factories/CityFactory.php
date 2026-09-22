<?php

namespace Modules\Support\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Support\Models\City;

class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'name' => [
                'ar' => $this->faker->city(),
                'en' => $this->faker->city(),
            ],
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'radius' => $this->faker->numberBetween(1, 100),
        ];
    }
}
