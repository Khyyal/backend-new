<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Reservations\Booking\CheckoutResult;

/**
 * The purchase and payment opened at checkout; `gateway` holds what the
 * client needs to pay online (null for other payment methods): for `moyasar`
 * `{type, publishable_key, amount_halalas, currency, given_id, description, methods, apple_pay}` (`given_id` is the payment uuid; `apple_pay` is `{merchant_id, label, country, supported_networks}` or null when off), for `tamara`
 * `{checkout_url, provider_reference, checkout_id, tamara_status}`, for `fake` (tests) `{checkout_url, provider_reference}`. `payment` is
 * null when promotions brought the total to zero.
 *
 * @property CheckoutResult $resource
 */
class CheckoutResource extends JsonResource
{
    #[ResponseField('purchase', 'object', 'The purchase.')]
    #[ResponseField('purchase.id', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseField('purchase.code', 'string', 'Public 6-character purchase code, unique; use it to look the purchase up.', example: 'K7QX2M')]
    #[ResponseField('purchase.status', 'string', 'Purchase status.', enum: PurchaseStatus::class, example: 'pending')]
    #[ResponseField('purchase.subtotal', 'integer', 'Before discounts, in halalas.', example: 30000)]
    #[ResponseField('purchase.discount_amount', 'integer', 'Discount in halalas.', example: 4500)]
    #[ResponseField('purchase.total_amount', 'integer', 'To pay, in halalas.', example: 25500)]
    #[ResponseField('purchase.coupon_code', 'string', 'Applied coupon.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('payment', 'object', 'The payment; null when nothing is paid now (free, or cash on arrival).', nullable: true)]
    #[ResponseField('payment.id', 'integer', 'Payment ID (Moyasar `given_id`).', example: 801)]
    #[ResponseField('payment.status', 'string', 'Payment status.', enum: PaymentStatus::class, example: 'pending')]
    #[ResponseField('payment.method', 'string', 'Payment method.', enum: PaymentMethod::class, example: 'online')]
    #[ResponseField('payment.payment_company', 'string', 'Gateway used.', nullable: true, example: 'moyasar')]
    #[ResponseField('payment.amount', 'integer', 'Amount in halalas.', example: 25500)]
    #[ResponseField('gateway', 'object', 'What the app needs to pay; null when no online payment is needed. `moyasar`: `{type, publishable_key, amount_halalas, currency, given_id, description}` — create the payment with the Moyasar SDK, pass `given_id` unchanged, open `transaction_url` for 3-D Secure. `tamara`: `{checkout_url, provider_reference, checkout_id, tamara_status}` — open `checkout_url`. `fake` (dev/test only): `{checkout_url, provider_reference}`. The status changes only when the provider confirms to the backend.', nullable: true, example: ['type' => 'moyasar', 'publishable_key' => 'pk_test_xxx', 'amount_halalas' => 25500, 'currency' => 'SAR', 'given_id' => '801', 'description' => 'Khyyal purchase #5521'])]
    public function toArray(Request $request): array
    {
        $purchase = $this->resource->purchase;
        $payment = $this->resource->payment;

        return [
            'purchase' => [
                'id' => $purchase->id,
                'code' => $purchase->code,
                'status' => $purchase->status->value,
                'subtotal' => $purchase->subtotal,
                'discount_amount' => $purchase->discount_amount,
                'total_amount' => $purchase->total_amount,
                'coupon_code' => $purchase->coupon_code,
            ],
            'payment' => $payment === null ? null : [
                'id' => $payment->id,
                'status' => $payment->status->value,
                'method' => $payment->method->value,
                'payment_company' => $payment->payment_company,
                'amount' => $payment->amount,
            ],
            'gateway' => $this->resource->gateway,
        ];
    }
}
