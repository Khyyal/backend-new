<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * Bookability of a service over the next days, starting today. Service types
 * without an availability implementation yet return `null`.
 */
class UpcomingAvailabilityService
{
    public const int DAYS = 15;

    public function __construct(private readonly RecreationRidingAvailability $recreationRiding) {}

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function forService(Service $service): ?array
    {
        $availability = match ($service->type) {
            ServiceType::RecreationRiding => $this->recreationRiding,
            default => null,
        };

        return $availability?->forService($service, CarbonImmutable::today(), self::DAYS);
    }
}
