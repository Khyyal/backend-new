<?php

namespace Modules\Reservations\Enums;

enum ClientPackageStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
