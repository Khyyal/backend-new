<?php

namespace Modules\Reservations\Booking;

use Modules\Services\Models\Service;
use Modules\Services\Models\Trainer;

/**
 * Serializes bookings that compete for the same places: the service row, and
 * the trainer row when a trainer's capacity is shared across services.
 * Must be called inside the transaction that inserts the reservation.
 */
class CapacityLock
{
    public function acquire(Service $service, ?int $trainerId = null): void
    {
        Service::query()->whereKey($service->getKey())->lockForUpdate()->first();

        if ($trainerId !== null) {
            Trainer::query()->whereKey($trainerId)->lockForUpdate()->first();
        }
    }
}
