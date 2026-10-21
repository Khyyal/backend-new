<?php

namespace Modules\Reservations\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Actions\CreatePayment;
use Modules\Purchase\Actions\CreatePurchase;
use Modules\Purchase\Contracts\Buyer;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Booking\CheckoutResult;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;

/**
 * Opens the purchase and its payment. A manual payment is taken by center
 * staff at the desk, so it succeeds and confirms the purchase right away;
 * other methods leave both pending for the gateway or the arrival. An online
 * payment is initialized with its gateway (the default one when no company
 * is given) so the client can pay.
 */
class StartCheckout
{
    public function __construct(
        private CreatePurchase $createPurchase,
        private CreatePayment $createPayment,
        private PaymentStateService $paymentState,
        private PurchaseStateService $purchaseState,
        private PaymentGatewayManager $gateways,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items  see CreatePurchase::execute()
     *
     * @throws ReservationNotAllowedException
     */
    public function execute(
        Buyer $buyer,
        Model $merchant,
        PurchaseSource $source,
        PaymentMethod $method,
        array $items,
        ?string $paymentCompany = null,
    ): CheckoutResult {
        if ($method === PaymentMethod::Manual && $source !== PurchaseSource::Center) {
            throw ReservationNotAllowedException::because('manual_payment_not_allowed');
        }

        return DB::transaction(function () use ($buyer, $merchant, $source, $method, $items, $paymentCompany): CheckoutResult {
            $purchase = $this->createPurchase->execute($buyer, $merchant, $source, $items);

            $isOnline = $method === PaymentMethod::Online;

            $payment = $this->createPayment->execute(
                $purchase,
                $method,
                (float) $purchase->total_amount,
                $isOnline ? ($paymentCompany ?? $this->gateways->getDefaultDriver()) : null,
            );

            if ($method === PaymentMethod::Manual) {
                $payment = $this->paymentState->markSucceeded($payment);
                $purchase = $this->purchaseState->confirm($purchase);
            }

            $gateway = $isOnline
                ? $this->gateways->driver($payment->payment_company)->initialize($purchase, $payment)
                : null;

            return new CheckoutResult($purchase, $payment, $gateway);
        });
    }
}
