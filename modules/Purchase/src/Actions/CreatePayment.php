<?php

namespace Modules\Purchase\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Events\PaymentCreated;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

class CreatePayment
{
    /**
     * @param  Purchase  $purchase  Target purchase (freshly reloaded internally to avoid stale state)
     * @param  PaymentMethod  $method  online / cash_on_arrival / manual
     * @param  float|int  $amount  Positive amount (matches single-currency application convention)
     * @param  string|null  $paymentCompany  Provider company when method=Online (e.g. "moyasar", "stripe")
     * @param  string|null  $paymentType  Provider/payment instrument type (e.g. "card", "apple_pay")
     * @param  array<string, mixed>  $metadata  Optional payment-level metadata
     */
    public function execute(
        Purchase $purchase,
        PaymentMethod $method,
        float|int $amount,
        ?string $paymentCompany = null,
        ?string $paymentType = null,
        array $metadata = [],
    ): Payment {
        $numericAmount = (float) $amount;
        if ($numericAmount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than 0.');
        }

        $purchaseFresh = Purchase::query()
            ->whereKey($purchase->getKey())
            ->first();

        if ($purchaseFresh === null) {
            throw new InvalidArgumentException('The provided purchase does not exist.');
        }

        $payment = DB::transaction(function () use (
            $purchaseFresh,
            $method,
            $numericAmount,
            $paymentCompany,
            $paymentType,
            $metadata,
        ): Payment {
            $payment = Payment::create([
                'purchase_id' => $purchaseFresh->getKey(),
                'amount' => $this->roundMoney($numericAmount),
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'payment_company' => $paymentCompany,
                'payment_type' => $paymentType,
                'payment_order_id' => null,
                'provider_data' => null,
                'metadata' => $metadata === [] ? null : $metadata,
                'paid_at' => null,
            ]);

            DB::afterCommit(static function () use ($payment): void {
                event(new PaymentCreated($payment));
            });

            return $payment;
        });

        $payment->load('purchase');

        return $payment;
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }
}
