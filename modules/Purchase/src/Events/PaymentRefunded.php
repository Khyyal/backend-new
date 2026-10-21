<?php

namespace Modules\Purchase\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Purchase\Models\Payment;

/**
 * Fired for every refund recorded on a payment, partial or full. The payment
 * becomes `refunded` once its whole amount has been refunded.
 */
class PaymentRefunded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly float $amount,
    ) {}
}
