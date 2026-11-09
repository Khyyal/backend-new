<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Billing\Data\SubscriptionCheckoutResult;

/**
 * The purchase and payment opened for a paid plan; `gateway` holds what the
 * center needs to pay online: for `moyasar` `{type, publishable_key, amount_halalas, currency, given_id, description, methods, apple_pay}` (`given_id` is the payment uuid; `apple_pay` is `{merchant_id, label, country, supported_networks}` or null when off),
 * for `tamara` `{checkout_url, provider_reference, checkout_id, tamara_status}`, for `fake` (tests) `{checkout_url, provider_reference}`.
 *
 * @property SubscriptionCheckoutResult $resource
 */
class SubscriptionCheckoutResource extends JsonResource
{
    #[ResponseField('purchase', 'object', 'The purchase: `id`, `status`, `subtotal`, `discount_amount`, `total_amount` (halalas).', example: ['id' => 6001, 'status' => 'pending', 'subtotal' => 49900, 'discount_amount' => 0, 'total_amount' => 49900])]
    #[ResponseField('payment', 'object', 'The payment: `id` (Moyasar `given_id`), `status`, `method`, `payment_company`, `amount`; null when none.', nullable: true, example: ['id' => 901, 'status' => 'pending', 'method' => 'online', 'payment_company' => 'moyasar', 'amount' => 49900])]
    #[ResponseField('gateway', 'object', 'What the app needs to pay; same shapes as the booking checkout (`moyasar`: type, publishable_key, amount_halalas, currency, given_id, description; `tamara`: checkout_url, provider_reference, checkout_id, tamara_status; `fake`: checkout_url, provider_reference).', nullable: true, example: ['type' => 'moyasar', 'publishable_key' => 'pk_test_xxx', 'amount_halalas' => 49900, 'currency' => 'SAR', 'given_id' => '901', 'description' => 'Khyyal Pro plan'])]
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
