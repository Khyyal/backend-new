<?php

namespace Modules\Promotion\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Promotion\Models\DiscountRedemption;

class CouponRedeemed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public DiscountRedemption $redemption,
    ) {}
}
