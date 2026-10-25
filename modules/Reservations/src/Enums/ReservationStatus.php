<?php

namespace Modules\Reservations\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * Statuses whose reservations hold a place against the service capacity.
     *
     * @return array<int, self>
     */
    public static function occupyingCapacity(): array
    {
        return [self::Pending, self::Confirmed, self::Completed, self::NoShow];
    }
}
