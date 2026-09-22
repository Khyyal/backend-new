<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Support\Models\ActionLog;

class ActionLogFactory extends Factory
{
    protected $model = ActionLog::class;

    public function definition(): array
    {
        return [
            'action' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
