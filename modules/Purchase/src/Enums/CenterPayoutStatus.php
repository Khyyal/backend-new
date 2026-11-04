<?php

namespace Modules\Purchase\Enums;

/**
 * pending -> paid | cancelled. A cancelled payout releases its entries into
 * the next one.
 */
enum CenterPayoutStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
