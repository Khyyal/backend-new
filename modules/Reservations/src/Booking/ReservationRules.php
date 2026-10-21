<?php

namespace Modules\Reservations\Booking;

use Modules\Reservations\Exceptions\ReservationNotAllowedException;

/**
 * Booking rules of one service type: validates a request against schedules,
 * blocks and capacity, and prices it.
 *
 * Capacity is read, not reserved: callers must run quote() inside a
 * transaction holding a lock on the service (or trainer) row and insert the
 * reservation before committing.
 */
interface ReservationRules
{
    /**
     * @throws ReservationNotAllowedException
     */
    public function quote(BookingRequest $request): BookingQuote;
}
