<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Support\Models\Device;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_identifier' => $this->faker->word(),
            'fcm_token' => Str::random(10),
            'deviceable_id' => $this->faker->randomDigitNotNull(),
            "deviceable_type" => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
