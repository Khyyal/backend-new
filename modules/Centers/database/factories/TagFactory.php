<?php

namespace Modules\Centers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Models\Tag;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => [
                'ar' => $this->faker->city(),
                'en' => $this->faker->city(),
            ],
        ];
    }
}
