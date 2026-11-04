<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Services\PaymentStateService;
use Throwable;

/**
 * A payment can succeed after its subscription checkout was released for
 * being unpaid (for example a late gateway webhook). The subscription is
 * already void, so the money goes straight back. A failed refund is logged
 * for manual follow-up.
 */
class RefundLateSubscriptionPayment
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private PaymentStateService $paymentState,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment->fresh('purchase.items');

        if ($payment === null || $payment->purchase->status !== PurchaseStatus::Cancelled) {
            return;
        }

        $isSubscription = $payment->purchase->items
            ->contains(fn ($item): bool => $item->purchasable_type === 'subscription');

        if (! $isSubscription || $payment->refundableAmount() <= 0) {
            return;
        }

        try {
            $amount = $payment->refundableAmount();

            if ($this->gateways->driver($payment->payment_company ?: null)->refund($payment, $amount)) {
                $this->paymentState->recordRefund($payment, $amount);

                return;
            }

            Log::error('Late subscription payment refund was declined by the gateway.', ['payment_id' => $payment->getKey()]);
        } catch (Throwable $exception) {
            Log::error('Late subscription payment refund failed.', ['payment_id' => $payment->getKey(), 'exception' => $exception]);
        }
    }
}
