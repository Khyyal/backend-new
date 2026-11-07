<?php

namespace Modules\Settlements\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Settlements\Models\CenterPayout;

class CenterPayoutPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CenterPayout $payout,
    ) {}
}
