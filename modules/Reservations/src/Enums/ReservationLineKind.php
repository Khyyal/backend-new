<?php

namespace Modules\Reservations\Enums;

enum ReservationLineKind: string
{
    case Ticket = 'ticket';
    case Addon = 'addon';
}
