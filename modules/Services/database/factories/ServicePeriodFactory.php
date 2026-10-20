<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServicePeriod;

class ServicePeriodFactory extends Factory
{
    protected $model = ServicePeriod::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'gender' => PeriodGender::Female,
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ];
    }

    public function forGender(PeriodGender $gender): static
    {
        return $this->state(['gender' => $gender]);
    }
}
