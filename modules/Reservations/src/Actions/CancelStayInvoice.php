<?php

namespace Modules\Reservations\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\StayInvoice;

/**
 * Cancels a stay invoice. A paid invoice (confirmed purchase) is refunded in
 * full through RefundPurchase; an unpaid one has its open payments and its
 * purchase cancelled; a free invoice is only marked cancelled. The stay is
 * not touched.
 */
class CancelStayInvoice
{
    public function __construct(
        private RefundPurchase $refundPurchase,
        private PurchaseStateService $purchaseState,
        private PaymentStateService $paymentState,
    ) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(StayInvoice $invoice, ?Model $cancelledBy = null, ?string $reason = null): StayInvoice
    {
        return DB::transaction(function () use ($invoice, $cancelledBy, $reason): StayInvoice {
            $locked = StayInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->isCancelled()) {
                throw ReservationNotAllowedException::because('invoice_already_cancelled');
            }

            $purchase = $locked->purchaseItem?->purchase;

            if ($purchase?->status === PurchaseStatus::Confirmed) {
                // Also marks the invoice cancelled (RefundPurchase::cancelSold); the reason is added below.
                $this->refundPurchase->execute($purchase, $cancelledBy);
                $locked->refresh();
            }

            if ($purchase?->status === PurchaseStatus::Pending) {
                $purchase->payments()
                    ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Processing])
                    ->get()
                    ->each(fn (Payment $payment) => $this->paymentState->cancel($payment));

                $this->purchaseState->cancel($purchase);
            }

            if ($locked->isCancelled()) {
                $locked->forceFill(['cancellation_reason' => $reason])->save();
            } else {
                $locked->markCancelled($cancelledBy, $reason);
            }

            return $locked;
        });
    }
}
