<?php

namespace Modules\Purchase\Enums;

enum PurchaseStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
