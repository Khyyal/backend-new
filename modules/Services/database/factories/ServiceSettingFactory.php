<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceSetting;

class ServiceSettingFactory extends Factory
{
    protected $model = ServiceSetting::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'key' => 'enable_cash_on_arrival',
            'value' => '1',
        ];
    }

    public function forKey(string $key, bool $enabled = true): static
    {
        return $this->state(['key' => $key, 'value' => $enabled ? '1' : '0']);
    }
}
