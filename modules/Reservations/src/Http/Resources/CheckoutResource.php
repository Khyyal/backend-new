<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
    public function toArray(Request $request): array
    {
        $purchase = $this->resource->purchase;
        $payment = $this->resource->payment;

        return [
            'purchase' => [
                'id' => $purchase->id,
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
