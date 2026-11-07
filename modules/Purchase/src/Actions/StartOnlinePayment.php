<?php

namespace Modules\Purchase\Actions;

use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

class StartOnlinePayment
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * Ask the payment's gateway to start the payment and keep the provider's own
     * reference (when it returns one) so its webhooks can find the payment.
     *
     * @return array<string, mixed> what the app needs to pay (see the checkout docs)
     */
    public function execute(Purchase $purchase, Payment $payment): array
    {
        $gateway = $this->gateways->driver($payment->payment_company)->initialize($purchase, $payment);

        $reference = (string) ($gateway['provider_reference'] ?? '');

        if ($reference !== '') {
            $payment->forceFill(['payment_order_id' => $reference])->save();
        }

        return $gateway;
    }
}
