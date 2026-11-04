<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Data\SubscriptionCheckoutResult;

/**
 * The purchase and payment opened for a paid plan; `gateway` holds what the
 * center needs to pay online.
 *
 * @property SubscriptionCheckoutResult $resource
 */
class SubscriptionCheckoutResource extends JsonResource
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
