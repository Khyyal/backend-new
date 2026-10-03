<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Billing\Models\Limit;

class LimitFactory extends Factory
{
    protected $model = Limit::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'key' => 'limit_'.Str::slug($name),
            'name' => ucfirst($name),
        ];
    }
}
