<?php

namespace Modules\Promotion\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Promotion\Models\Discount;

class DiscountCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Discount $discount,
    ) {}
}
