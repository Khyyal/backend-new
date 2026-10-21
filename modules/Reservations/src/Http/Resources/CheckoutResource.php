<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservations\Booking\CheckoutResult;

/**
 * The purchase and payment opened at checkout; `gateway` holds what the
 * client needs to pay online (null for other payment methods).
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
                'total_amount' => (float) $purchase->total_amount,
            ],
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status->value,
                'method' => $payment->method->value,
                'payment_company' => $payment->payment_company,
                'amount' => (float) $payment->amount,
            ],
            'gateway' => $this->resource->gateway,
        ];
    }
}
