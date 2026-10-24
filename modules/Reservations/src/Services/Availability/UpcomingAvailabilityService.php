<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * Bookability of a service over the next days, starting today (events use
 * their own dates). Service types without an availability implementation yet
 * return `null`.
 */
class UpcomingAvailabilityService
{
    public const int DAYS = 15;

    public function __construct(
        private readonly RecreationRidingAvailability $recreationRiding,
        private readonly TrainingServiceAvailability $training,
        private readonly EventAvailability $event,
    ) {}

    /**
     * @return array<int, array<mixed>>|null days, or days keyed by trainer id for training
     */
    public function forService(Service $service): ?array
    {
        $availability = match ($service->type) {
            ServiceType::RecreationRiding => $this->recreationRiding,
            ServiceType::Training => $this->training,
            ServiceType::Event => $this->event,
            default => null,
        };

        return $availability?->forService($service, CarbonImmutable::today(), self::DAYS);
    }
}
