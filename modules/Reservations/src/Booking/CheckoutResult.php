<?php

namespace Modules\Reservations\Booking;

use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

/**
 * The purchase and payment opened for reservations or a package.
 */
final readonly class CheckoutResult
{
    /**
     * @param  array<string, mixed>|null  $gateway  what the client needs to pay online, from PaymentGateway::initialize()
     */
    public function __construct(
        public Purchase $purchase,
        public Payment $payment,
        public ?array $gateway = null,
    ) {}
}
