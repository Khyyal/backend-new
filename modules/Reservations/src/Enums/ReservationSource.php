<?php

namespace Modules\Reservations\Enums;

enum ReservationSource: string
{
    case Client = 'client';
    case Center = 'center';
}
