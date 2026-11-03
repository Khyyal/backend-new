<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Models\Device;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_identifier' => Str::uuid()->toString(),
            'fcm_token' => Str::random(10),
            'platform' => $this->faker->randomElement(DevicePlatform::cases()),
            'locale' => $this->faker->randomElement(['en', 'ar']),
            'last_seen_at' => Carbon::now(),
            'deviceable_id' => $this->faker->randomDigitNotNull(),
            'deviceable_type' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * A device registered on launch, not linked to any user yet.
     */
    public function unowned(): static
    {
        return $this->state(fn (): array => [
            'deviceable_id' => null,
            'deviceable_type' => null,
        ]);
    }

    public function ownedBy(Model $owner): static
    {
        return $this->state(fn (): array => [
            'deviceable_id' => $owner->getKey(),
            'deviceable_type' => $owner->getMorphClass(),
        ]);
    }
}
