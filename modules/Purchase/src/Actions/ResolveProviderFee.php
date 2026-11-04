<?php

namespace Modules\Purchase\Actions;

use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Models\Payment;

/**
 * What the payment provider charged the platform for an online payment: the
 * fee the provider reported (Moyasar sends `fee` in halalas), else the
 * configured rate for the gateway. Cash and manual payments have none.
 */
class ResolveProviderFee
{
    public function execute(Payment $payment): ?int
    {
        if ($payment->method !== PaymentMethod::Online) {
            return null;
        }

        $reported = data_get($payment->provider_data, 'moyasar_payment.fee');

        if (is_numeric($reported)) {
            return (int) $reported;
        }

        $rate = config("purchase.provider_fees.{$payment->payment_company}");

        if (! is_array($rate)) {
            return 0;
        }

        return intdiv($payment->amount * (int) $rate['percentage_bps'], 10000) + (int) $rate['fixed_amount'];
    }
}
