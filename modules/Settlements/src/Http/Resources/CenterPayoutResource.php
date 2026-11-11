<?php

namespace Modules\Settlements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Settlements\Enums\CenterPayoutStatus;
use Modules\Settlements\Models\CenterPayout;

/**
 * A payout to a center. `amount` is in halalas; the bank details are those
 * snapshotted when the payout was created.
 *
 * @property CenterPayout $resource
 */
class CenterPayoutResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Payout ID.', example: 17)]
    #[ResponseField('center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('center', 'object', 'The center (`id`, `name`). Present when loaded.', required: false, example: ['id' => 8, 'name' => 'Al Fursan Stables'])]
    #[ResponseField('period_end', 'string', 'Entries available up to this time are included (ISO-8601).', example: '2026-06-07T00:00:00+00:00')]
    #[ResponseField('amount', 'integer', 'Amount to transfer in halalas.', example: 812500)]
    #[ResponseField('entries_count', 'integer', 'Ledger entries included.', example: 24)]
    #[ResponseField('status', 'string', '`pending` until finance transfers it (`paid`) or cancels it.', enum: CenterPayoutStatus::class, example: 'pending')]
    #[ResponseField('iban', 'string', 'Destination IBAN at creation time; null when the center had not set one.', nullable: true, example: 'SA0380000000608010167519')]
    #[ResponseField('account_name', 'string', 'Account holder; null when not set.', nullable: true, example: 'Al Fursan Stables LLC')]
    #[ResponseField('bank_name', 'string', 'Bank; null when not set.', nullable: true, example: 'Al Rajhi Bank')]
    #[ResponseField('reference', 'string', 'Bank transfer reference once paid.', nullable: true, example: 'No-example')]
    #[ResponseField('paid_at', 'string', 'Transfer time (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('cancelled_at', 'string', 'Cancellation time (ISO-8601).', nullable: true, example: 'No-example')]
    #[ResponseField('entries', 'object[]', 'Ledger entries paid (`CenterLedgerEntryResource`). Present on show.', required: false)]
    #[ResponseField('entries[].id', 'integer', 'Ledger entry ID.', example: 3001)]
    #[ResponseField('entries[].purchase_id', 'integer', 'Purchase it comes from.', example: 5521)]
    #[ResponseField('entries[].payment_id', 'integer', 'Payment it comes from; null for fee and subsidy entries.', nullable: true, example: 801)]
    #[ResponseField('entries[].type', 'string', '`payment` (credit), `refund`, `platform_fee` (debit, also on cash sales), `fee_reversal`, `platform_subsidy` (platform-funded discount), `subsidy_reversal`.', enum: ['payment', 'platform_subsidy', 'subsidy_reversal', 'platform_fee', 'fee_reversal', 'refund'], example: 'payment')]
    #[ResponseField('entries[].amount', 'integer', 'Signed amount in halalas: positive when the platform owes the center, negative when the center owes the platform.', example: 29325)]
    #[ResponseField('entries[].available_at', 'string', 'When it leaves the hold period and becomes payable (ISO-8601).', example: '2026-06-08T10:00:00+00:00')]
    #[ResponseField('entries[].payout_id', 'integer', 'Payout that pays it; null while unpaid.', nullable: true, example: 'No-example')]
    #[ResponseField('entries[].created_at', 'string', 'Entry time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
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
