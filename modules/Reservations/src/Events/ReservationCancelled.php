<?php

namespace Modules\Reservations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Reservations\Models\Reservation;

class ReservationCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
    ) {}
}
