<?php

namespace Modules\Billing\Data;

use Modules\Billing\Models\Subscription;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

/**
 * The subscription opened at checkout and, for a paid plan, the purchase and
 * online payment that activate it. Free and trial plans start right away
 * without a purchase.
 */
final readonly class SubscriptionCheckoutResult
{
    /**
     * @param  array<string, mixed>|null  $gateway  what the center needs to pay online, from PaymentGateway::initialize()
     */
    public function __construct(
        public Subscription $subscription,
        public ?Purchase $purchase = null,
        public ?Payment $payment = null,
        public ?array $gateway = null,
    ) {}
}
