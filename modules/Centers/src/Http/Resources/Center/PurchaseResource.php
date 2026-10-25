<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Purchase\Models\Purchase;

/**
 * A purchase as it appears in the center's list.
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
            'client' => $this->whenLoaded('buyer', fn (): ?array => $purchase->buyer === null ? null : [
                'id' => $purchase->buyer->id,
                'full_name' => $purchase->buyer->full_name,
                'phone_number' => $purchase->buyer->phone_number,
            ]),
            'items_count' => $this->whenCounted('items'),
            'subtotal' => (float) $purchase->subtotal,
            'discount_amount' => (float) $purchase->discount_amount,
            'tax_amount' => (float) $purchase->tax_amount,
            'total_amount' => (float) $purchase->total_amount,
            'created_at' => $purchase->created_at?->toIso8601String(),
        ];
    }
}
