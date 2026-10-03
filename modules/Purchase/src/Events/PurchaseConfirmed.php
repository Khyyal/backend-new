<?php

namespace Modules\Purchase\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Purchase\Models\Purchase;

class PurchaseConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Purchase $purchase,
    ) {}
}
