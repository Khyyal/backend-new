<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Billing\Models\Feature;

class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'key' => 'feat_'.Str::slug($name),
            'name' => ucfirst($name),
            'is_quota' => fake()->boolean(30),
        ];
    }
}
