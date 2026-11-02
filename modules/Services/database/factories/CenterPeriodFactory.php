<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Models\CenterPeriod;

class CenterPeriodFactory extends Factory
{
    protected $model = CenterPeriod::class;

    public function definition(): array
    {
        return [
            'center_id' => CenterFactory::new(),
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
