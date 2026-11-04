<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Purchase\Models\CenterLedgerEntry;

/**
 * One movement of a center's balance in halalas: positive is owed to the
 * center, negative is owed by it.
 *
 * @property CenterLedgerEntry $resource
 */
class CenterLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'purchase_id' => $entry->purchase_id,
            'payment_id' => $entry->payment_id,
            'type' => $entry->type->value,
            'amount' => $entry->amount,
            'available_at' => $entry->available_at->toIso8601String(),
            'payout_id' => $entry->payout_id,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
