<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Purchase\Models\CenterPayout;

/**
 * A payout to a center. `amount` is in halalas; the bank details are those
 * snapshotted when the payout was created.
 *
 * @property CenterPayout $resource
 */
class CenterPayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payout = $this->resource;

        return [
            'id' => $payout->id,
            'center_id' => $payout->center_id,
            'center' => $this->whenLoaded('center', fn (): array => [
                'id' => $payout->center->id,
                'name' => $payout->center->name,
            ]),
            'period_end' => $payout->period_end->toIso8601String(),
            'amount' => $payout->amount,
            'entries_count' => $payout->entries_count,
            'status' => $payout->status->value,
            'iban' => $payout->iban,
            'account_name' => $payout->account_name,
            'bank_name' => $payout->bank_name,
            'reference' => $payout->reference,
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'cancelled_at' => $payout->cancelled_at?->toIso8601String(),
            'entries' => CenterLedgerEntryResource::collection($this->whenLoaded('entries')),
            'created_at' => $payout->created_at?->toIso8601String(),
        ];
    }
}
