<?php

namespace Modules\Purchase\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Purchase\Models\Purchase;

/**
 * A client purchase in the platform-wide list. `payment_method` and `payment_status` come from the latest
 * payment (`null` without one). Amounts are halalas; `platform_fee_amount` is what the center owes the platform
 * (0 once reversed).
 *
 * @property Purchase $resource
 */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $purchase = $this->resource;

        return [
            'id' => $purchase->id,
            'status' => $purchase->status->value,
            'source' => $purchase->source->value,
            'channel' => $purchase->channel?->value,
            'payment_method' => $purchase->latestPayment?->method->value,
            'payment_status' => $purchase->latestPayment?->status->value,
            'client' => $purchase->buyer === null ? null : [
                'id' => $purchase->buyer->id,
                'full_name' => $purchase->buyer->full_name,
                'phone_number' => $purchase->buyer->phone_number,
            ],
            'center' => $purchase->merchant === null ? null : [
                'id' => $purchase->merchant->id,
                'name' => $purchase->merchant->name,
            ],
            'items_count' => $purchase->items_count,
            'subtotal' => $purchase->subtotal,
            'discount_amount' => $purchase->discount_amount,
            'tax_amount' => $purchase->tax_amount,
            'total_amount' => $purchase->total_amount,
            'platform_fee_amount' => $purchase->fee?->owedAmount() ?? 0,
            'created_at' => $purchase->created_at?->toIso8601String(),
        ];
    }
}
