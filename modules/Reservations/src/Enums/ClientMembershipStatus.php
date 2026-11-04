<?php

namespace Modules\Reservations\Enums;

enum ClientMembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
