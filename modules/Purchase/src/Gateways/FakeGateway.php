<?php

namespace Modules\Purchase\Gateways;

use Illuminate\Support\Str;
use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

class FakeGateway implements PaymentGateway
{
    protected static array $nextStatus = [];

    public static function setNextStatus(string $paymentId, PaymentStatus $status): void
    {
        static::$nextStatus[$paymentId] = $status;
    }

    public static function clearNextStatus(?string $paymentId = null): void
    {
        if ($paymentId === null) {
            static::$nextStatus = [];

            return;
        }

        unset(static::$nextStatus[$paymentId]);
    }

    public function initialize(Purchase $purchase, Payment $payment): array
    {
        return [
            'checkout_url' => 'https://fake.example/checkout/'.Str::random(16),
            'provider_reference' => 'fake_ord_'.Str::random(12),
        ];
    }

    public function verify(Payment $payment): PaymentStatus
    {
        $key = (string) $payment->getKey();

        if (isset(static::$nextStatus[$key])) {
            return static::$nextStatus[$key];
        }

        return PaymentStatus::Pending;
    }

    public function capture(Payment $payment): PaymentStatus
    {
        $key = (string) $payment->getKey();

        if (isset(static::$nextStatus[$key])) {
            return static::$nextStatus[$key];
        }

        return PaymentStatus::Succeeded;
    }

    public function cancel(Payment $payment): PaymentStatus
    {
        return PaymentStatus::Cancelled;
    }

    public function refund(Payment $payment, $amount): bool
    {
        return true;
    }
}
