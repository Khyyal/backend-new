<?php

namespace Modules\Reservations\Enums;

enum ClientMembershipLogType: string
{
    case Invite = 'invite';
    case CheckIn = 'check_in';
}
