<?php

namespace Modules\Reservations\Booking;

use Modules\Centers\Models\Horse;
use Modules\Services\Models\Service;
use Modules\Services\Models\Trainer;

/**
 * Serializes bookings that compete for the same places: the service row, and
 * the trainer row when a trainer's capacity is shared across services, and
 * the horse row for a stabling stay (a horse is in one stable at a time).
 * Must be called inside the transaction that inserts the reservation.
 */
class CapacityLock
{
    public function acquire(Service $service, ?int $trainerId = null, ?int $horseId = null): void
    {
        Service::query()->whereKey($service->getKey())->lockForUpdate()->first();

        if ($trainerId !== null) {
            Trainer::query()->whereKey($trainerId)->lockForUpdate()->first();
        }

        if ($horseId !== null) {
            Horse::query()->whereKey($horseId)->lockForUpdate()->first();
        }
    }
}
