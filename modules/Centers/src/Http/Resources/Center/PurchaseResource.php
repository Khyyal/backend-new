<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Modules\Purchase\Models\Purchase;

/**
 * A purchase as it appears in the center's list. Amounts are only included
 * when the user may view purchase prices (`purchases.view_prices`).
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
            $this->mergeWhen($this->canViewPrices($request), fn (): array => [
                'subtotal' => (float) $purchase->subtotal,
                'discount_amount' => (float) $purchase->discount_amount,
                'tax_amount' => (float) $purchase->tax_amount,
                'total_amount' => (float) $purchase->total_amount,
            ]),
            'created_at' => $purchase->created_at?->toIso8601String(),
        ];
    }

    protected function canViewPrices(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('viewPurchasePrices', $request->route('center'));
    }
}
