<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Support\Models\OTP;

class OTPFactory extends Factory
{
    protected $model = OTP::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->word(),
            'phone_number' => $this->faker->phoneNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
