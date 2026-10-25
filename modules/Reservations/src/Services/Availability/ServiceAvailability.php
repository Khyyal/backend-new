<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Modules\Services\Models\Service;

/**
 * Day-by-day bookability of one service type, as shown to clients.
 */
interface ServiceAvailability
{
    /**
     * Eager loads on the Service needed to compute the range without extra queries.
     *
     * @return array<int|string, string|\Closure>
     */
    public function relations(CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Days for the service, or days keyed by schedule owner (e.g. trainer id).
     *
     * @return array<int, array<mixed>>
     */
    public function forService(Service $service, CarbonImmutable $from, int $days): array;
}
