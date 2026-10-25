<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;
use Modules\Services\Models\TrainingService;
use Modules\Support\Enums\ActivationStatus;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Defaults to an active training service; use forServiceable() for other types.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['ar' => fake()->words(2, true), 'en' => fake()->words(2, true)],
            'description' => ['ar' => fake()->sentence(), 'en' => fake()->sentence()],
            'status' => ActivationStatus::ACTIVE,
            'type' => ServiceType::Training,
            'center_id' => Center::factory(),
            'serviceable_type' => (new TrainingService)->getMorphClass(),
            'serviceable_id' => fn () => TrainingServiceFactory::new()->create()->getKey(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => ActivationStatus::INACTIVE]);
    }

    public function forServiceable(Model $serviceable, ServiceType $type): static
    {
        return $this->state([
            'type' => $type,
            'serviceable_type' => $serviceable->getMorphClass(),
            'serviceable_id' => $serviceable->getKey(),
        ]);
    }
}
