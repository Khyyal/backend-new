<?php

namespace Modules\Notifications\Enums;

enum DeliveryStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
