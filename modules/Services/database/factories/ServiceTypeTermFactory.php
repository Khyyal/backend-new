<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\ServiceTypeTerm;

class ServiceTypeTermFactory extends Factory
{
    protected $model = ServiceTypeTerm::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(ServiceType::cases())->value,
            'terms' => ['ar' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
            'center_id' => Center::factory(),
        ];
    }
}
