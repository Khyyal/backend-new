<?php

namespace Modules\Settlements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Settlements\Models\CenterLedgerEntry;

/**
 * One movement of a center's balance in halalas: positive is owed to the
 * center, negative is owed by it.
 *
 * @property CenterLedgerEntry $resource
 */
class CenterLedgerEntryResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Ledger entry ID.', example: 3001)]
    #[ResponseField('purchase_id', 'integer', 'Purchase it comes from.', example: 5521)]
    #[ResponseField('payment_id', 'integer', 'Payment it comes from; null for fee and subsidy entries.', nullable: true, example: 801)]
    #[ResponseField('type', 'string', '`payment` (credit), `refund`, `platform_fee` (debit, also on cash sales), `fee_reversal`, `platform_subsidy` (platform-funded discount), `subsidy_reversal`.', enum: ['payment', 'platform_subsidy', 'subsidy_reversal', 'platform_fee', 'fee_reversal', 'refund'], example: 'payment')]
    #[ResponseField('amount', 'integer', 'Signed amount in halalas: positive when the platform owes the center, negative when the center owes the platform.', example: 29325)]
    #[ResponseField('available_at', 'string', 'When it leaves the hold period and becomes payable (ISO-8601).', example: '2026-06-08T10:00:00+00:00')]
    #[ResponseField('payout_id', 'integer', 'Payout that pays it; null while unpaid.', nullable: true, example: 'No-example')]
    #[ResponseField('created_at', 'string', 'Entry time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
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
