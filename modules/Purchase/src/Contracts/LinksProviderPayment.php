<?php

namespace Modules\Purchase\Contracts;

use Modules\Purchase\Exceptions\PaymentVerificationException;
use Modules\Purchase\Models\Payment;

/**
 * Gateways where the provider payment is created by the app, so its id is only known later.
 */
interface LinksProviderPayment
{
    /**
     * Attach the provider payment to ours after checking it really belongs to this payment.
     *
     * @throws PaymentVerificationException when it belongs to another payment
     */
    public function link(Payment $payment, string $providerPaymentId): void;
}
