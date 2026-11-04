<?php

namespace Modules\Reservations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Membership;
use Modules\Services\Models\Service;

class ClientMembershipFactory extends Factory
{
    protected $model = ClientMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => ClientFactory::new(),
            'service_id' => fn () => ServiceFactory::new()
                ->forServiceable(Membership::factory()->create(), ServiceType::Membership)
                ->create()
                ->getKey(),
            'membership_id' => fn (array $attributes) => Service::query()->findOrFail($attributes['service_id'])->serviceable_id,
            'price' => fake()->numberBetween(20000, 200000),
            'status' => ClientMembershipStatus::Active,
            'visit_service_id' => null,
            'max_visits_per_day' => null,
            'duration' => 3,
            'expires_at' => now()->addMonths(3),
        ];
    }

    public function withVisits(Service $visitService, int $maxVisitsPerDay): static
    {
        return $this->state([
            'visit_service_id' => $visitService->getKey(),
            'max_visits_per_day' => $maxVisitsPerDay,
        ]);
    }

    public function pending(): static
    {
        return $this->state(['status' => ClientMembershipStatus::Pending, 'expires_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
