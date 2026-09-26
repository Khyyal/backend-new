<?php

namespace Modules\Purchase\Contracts;

use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

interface PaymentGateway
{
    public function initialize(Purchase $purchase, Payment $payment): array;

    public function verify(Payment $payment): PaymentStatus;

    public function capture(Payment $payment): PaymentStatus;

    public function cancel(Payment $payment): PaymentStatus;

    public function refund(Payment $payment, $amount): bool;
}
