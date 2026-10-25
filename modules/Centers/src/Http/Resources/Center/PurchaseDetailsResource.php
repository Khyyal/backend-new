<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\PurchaseItem;

/**
 * A purchase with its line items and payments.
 */
class PurchaseDetailsResource extends PurchaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'items' => $this->whenLoaded('items', fn (): array => $this->resource->items->map(fn (PurchaseItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'purchasable_type' => class_basename($item->purchasable_type),
                'purchasable_id' => $item->purchasable_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'discount_amount' => (float) $item->discount_amount,
                'tax_amount' => (float) $item->tax_amount,
                'total_amount' => (float) $item->total_amount,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn (): array => $this->resource->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'payment_company' => $payment->payment_company,
                'amount' => (float) $payment->amount,
                'refunded_amount' => (float) $payment->refunded_amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'refunded_at' => $payment->refunded_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
