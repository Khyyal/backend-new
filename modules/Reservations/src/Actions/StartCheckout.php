<?php

namespace Modules\Reservations\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Promotions\Contracts\PromotionRedeemer;
use Modules\Promotions\Data\PriceAdjustment;
use Modules\Promotions\Exceptions\CouponRejectedException;
use Modules\Promotions\Exceptions\PromotionUnavailableException;
use Modules\Purchase\Actions\CreatePayment;
use Modules\Purchase\Actions\CreatePurchase;
use Modules\Purchase\Actions\RecordPurchaseFee;
use Modules\Purchase\Actions\StartOnlinePayment;
use Modules\Purchase\Contracts\Buyer;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Booking\CheckoutPricer;
use Modules\Reservations\Booking\CheckoutQuote;
use Modules\Reservations\Booking\CheckoutResult;
use Modules\Reservations\Booking\DiscountInput;
use Modules\Reservations\Exceptions\PriceChangedException;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;

/**
 * Opens the purchase and its payment. A client buying from a center gets the
 * center's running promotions (and the coupon they sent); their usage is
 * consumed in the same transaction. A manual payment is taken by center staff
 * at the desk, so it succeeds and confirms the purchase right away; other
 * methods leave both pending for the gateway or the arrival. An online
 * payment is initialized with its gateway (the default one when no company is
 * given) so the client can pay. A purchase discounted to nothing is confirmed
 * without a payment. A center selling records the platform fee it owes on
 * the purchase, priced for the channel it was made on.
 */
class StartCheckout
{
    public function __construct(
        private CreatePurchase $createPurchase,
        private CreatePayment $createPayment,
        private PaymentStateService $paymentState,
        private PurchaseStateService $purchaseState,
        private PaymentGatewayManager $gateways,
        private CheckoutPricer $pricer,
        private PromotionRedeemer $redeemer,
        private RecordPurchaseFee $recordFee,
        private StartOnlinePayment $startOnlinePayment,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items  see CreatePurchase::execute(); a `service_id` lets promotions target the item
     *
     * @throws ReservationNotAllowedException
     * @throws CouponRejectedException when the coupon sent cannot be applied
     * @throws PriceChangedException when the total differs from the expected one
     * @throws PromotionUnavailableException when an offer ran out while checking out
     */
    public function execute(
        Buyer $buyer,
        Model $merchant,
        PurchaseSource $source,
        PaymentMethod $method,
        array $items,
        ?string $paymentCompany = null,
        DiscountInput $discount = new DiscountInput,
        ?PurchaseChannel $channel = null,
    ): CheckoutResult {
        if ($method === PaymentMethod::Manual && $source !== PurchaseSource::Center) {
            throw ReservationNotAllowedException::because('manual_payment_not_allowed');
        }

        $items = array_values($items);
        $channel = $this->channel($merchant, $channel);

        return DB::transaction(function () use ($buyer, $merchant, $source, $method, $items, $paymentCompany, $discount, $channel): CheckoutResult {
            $quote = $this->quote($buyer, $merchant, $items, $discount);

            foreach ($items as $index => $item) {
                $items[$index]['discount_amount'] = $quote?->lineDiscount($index) ?? 0;
            }

            $purchase = $this->createPurchase->execute($buyer, $merchant, $source, $items, channel: $channel);

            if ($quote !== null && $quote->adjustments() !== []) {
                $this->recordDiscounts($purchase, $buyer, $quote);
            }

            if ($merchant instanceof Center) {
                $this->recordFee->execute($purchase, $merchant);
            }

            if ($purchase->total_amount <= 0) {
                return new CheckoutResult($this->purchaseState->confirm($purchase), null);
            }

            $isOnline = $method === PaymentMethod::Online;

            $payment = $this->createPayment->execute(
                $purchase,
                $method,
                $purchase->total_amount,
                $isOnline ? ($paymentCompany ?? $this->gateways->getDefaultDriver()) : null,
            );

            if ($method === PaymentMethod::Manual) {
                $payment = $this->paymentState->markSucceeded($payment);
                $purchase = $this->purchaseState->confirm($purchase);
            }

            $gateway = $isOnline
                ? $this->startOnlinePayment->execute($purchase, $payment)
                : null;

            return new CheckoutResult($purchase, $payment, $gateway);
        });
    }

    /**
     * A center's own app or website only counts as such when the center has
     * one; otherwise the channel is unknown.
     */
    private function channel(Model $merchant, ?PurchaseChannel $channel): ?PurchaseChannel
    {
        if ($channel?->isCenterBranded() && ! ($merchant instanceof Center && $merchant->custom_site_enabled)) {
            return null;
        }

        return $channel;
    }

    /**
     * Promotions apply to a client buying from a center.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function quote(Buyer $buyer, Model $merchant, array $items, DiscountInput $discount): ?CheckoutQuote
    {
        if (! $buyer instanceof Client || ! $merchant instanceof Center) {
            return null;
        }

        $quote = $this->pricer->quote($merchant, $buyer, $items, $discount->couponCode);
        $coupon = $quote->coupon();

        if ($discount->hasCoupon() && $coupon !== null && ! $coupon->isApplied()) {
            throw new CouponRejectedException($coupon->check);
        }

        if ($discount->expectedTotalAmount !== null && $discount->expectedTotalAmount !== $quote->totalAmount()) {
            throw new PriceChangedException($quote);
        }

        return $quote;
    }

    /**
     * Snapshots the applied discounts on the purchase and consumes their usage.
     */
    private function recordDiscounts(Purchase $purchase, Buyer $buyer, CheckoutQuote $quote): void
    {
        $itemIds = $purchase->items->sortBy('id')->pluck('id')->values();

        foreach ($quote->adjustments() as $adjustment) {
            /** @var PriceAdjustment $adjustment */
            $purchase->adjustments()->create([
                'purchase_item_id' => $adjustment->lineIndex !== null ? $itemIds[$adjustment->lineIndex] : null,
                'type' => $adjustment->type(),
                'promotion_id' => $adjustment->promotionId,
                'coupon_id' => $adjustment->couponId,
                'code' => $adjustment->couponCode,
                'label' => $adjustment->label,
                'discount_type' => $adjustment->discountType->value,
                'discount_value' => $adjustment->discountValue,
                'amount' => $adjustment->amount,
                'funded_by' => $adjustment->fundedBy->value,
                'center_share_amount' => $adjustment->centerShareAmount,
            ]);
        }

        if ($quote->coupon()?->isApplied()) {
            $purchase->update(['coupon_code' => $quote->coupon()->code]);
        }

        $this->redeemer->redeem($purchase->getKey(), $buyer->getKey(), $quote->adjustments());
    }
}
