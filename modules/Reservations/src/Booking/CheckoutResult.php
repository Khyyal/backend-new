<?php

namespace Modules\Reservations\Booking;

use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

/**
 * The purchase and payment opened for reservations or a package.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public Purchase $purchase,
        public Payment $payment,
    ) {}
}
