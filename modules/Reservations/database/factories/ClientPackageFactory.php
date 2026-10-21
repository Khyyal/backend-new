<?php

namespace Modules\Reservations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\ClientPackage;
use Modules\Services\Database\Factories\ServicePackageFactory;
use Modules\Services\Models\ServicePackage;

class ClientPackageFactory extends Factory
{
    protected $model = ClientPackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sessions = fake()->numberBetween(4, 12);

        return [
            'client_id' => ClientFactory::new(),
            'service_package_id' => ServicePackageFactory::new(),
            'service_id' => fn (array $attributes) => ServicePackage::query()->findOrFail($attributes['service_package_id'])->service_id,
            'price_option_id' => fn (array $attributes) => ServicePackage::query()->findOrFail($attributes['service_package_id'])->price_option_id,
            'total_sessions' => $sessions,
            'remaining_sessions' => $sessions,
            'price' => fake()->numberBetween(200, 2000),
            'status' => ClientPackageStatus::Active,
            'expires_at' => now()->addMonths(3),
        ];
    }

    public function forServicePackage(ServicePackage $package): static
    {
        return $this->state([
            'service_package_id' => $package->getKey(),
            'service_id' => $package->service_id,
            'price_option_id' => $package->price_option_id,
            'total_sessions' => $package->count,
            'remaining_sessions' => $package->count,
            'price' => $package->price,
        ]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
