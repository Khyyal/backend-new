<?php

namespace Modules\Purchase\Services;

use Modules\Purchase\Contracts\LinksProviderPayment;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Exceptions\PaymentVerificationException;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Payment;

/**
 * Asks the provider what happened to a payment, for apps coming back from the provider's flow.
 * The app's own word is never used: only the provider's answer moves the payment.
 */
class PaymentVerificationService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentStateService $paymentState,
    ) {}

    /**
     * @param  string|null  $providerPaymentId  the provider's payment id, needed once for gateways where the app creates the payment (Moyasar)
     *
     * @throws PaymentVerificationException
     */
    public function verify(Payment $payment, ?string $providerPaymentId = null): Payment
    {
        if ($payment->method !== PaymentMethod::Online || $payment->payment_company === null) {
            throw new PaymentVerificationException('Only online payments can be verified.');
        }

        if (in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::Refunded], true)) {
            return $payment;
        }

        $gateway = $this->gateways->driver($payment->payment_company);

        if (($payment->payment_order_id ?? '') === '' && $gateway instanceof LinksProviderPayment && $providerPaymentId !== null) {
            $gateway->link($payment, $providerPaymentId);
        }

        if (($payment->payment_order_id ?? '') === '') {
            return $payment;
        }

        $status = $gateway->verify($payment);

        if ($status === PaymentStatus::Processing && $payment->payment_company === 'tamara') {
            $status = $gateway->authorize($payment);
        }

        return $this->paymentState->applyStatus($payment->refresh(), $status);
    }
}
