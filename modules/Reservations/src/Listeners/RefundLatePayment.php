<?php

namespace Modules\Reservations\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Services\PaymentStateService;
use Throwable;

/**
 * A payment can succeed after its checkout was released for being unpaid
 * (for example a late gateway webhook). Its reservations are gone, so the
 * money goes straight back. A failed refund is logged for manual follow-up.
 */
class RefundLatePayment
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

        $isBooking = $payment->purchase->items
            ->contains(fn ($item): bool => in_array($item->purchasable_type, ['reservation', 'client_package'], true));

        if (! $isBooking || $payment->refundableAmount() <= 0) {
            return;
        }

        try {
            $amount = $payment->refundableAmount();

            if ($this->gateways->driver($payment->payment_company ?: null)->refund($payment, $amount)) {
                $this->paymentState->recordRefund($payment, $amount);

                return;
            }

            Log::error('Late payment refund was declined by the gateway.', ['payment_id' => $payment->getKey()]);
        } catch (Throwable $exception) {
            Log::error('Late payment refund failed.', ['payment_id' => $payment->getKey(), 'exception' => $exception]);
        }
    }
}
