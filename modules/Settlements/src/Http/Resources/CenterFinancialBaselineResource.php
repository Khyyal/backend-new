<?php

namespace Modules\Settlements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Models\CenterFinancialBaseline;
use Modules\Settlements\Models\CenterLedgerEntry;

/**
 * A center's financial baseline in halalas: the figures finance set
 * (`target_*`), what the migrated data said (`migrated_*`) and the
 * difference between them.
 *
 * @property CenterFinancialBaseline $resource
 */
class CenterFinancialBaselineResource extends JsonResource
{
    #[ResponseField('center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('status', 'string', '`pending` blocks the center\'s payouts until confirmed; `not_required` means the center has no baseline.', enum: ['not_required', 'pending', 'confirmed'], example: 'confirmed')]
    #[ResponseField('cutoff_at', 'string', 'Moment the figures are true as of (ISO-8601); null until confirmed.', nullable: true, example: '2026-11-10T21:00:00+00:00')]
    #[ResponseField('target_balance', 'integer', 'Unpaid balance finance confirmed at the cutoff.', nullable: true, example: 812500)]
    #[ResponseField('target_platform_fees', 'integer', 'Platform fees total finance confirmed.', nullable: true, example: 1520000)]
    #[ResponseField('target_paid_out', 'integer', 'Transferred to the center before the migration.', nullable: true, example: 4300000)]
    #[ResponseField('target_gross_sales', 'integer', 'Counted sales total finance confirmed.', nullable: true, example: 15200000)]
    #[ResponseField('target_refunds', 'integer', 'Refunds total finance confirmed.', nullable: true, example: 240000)]
    #[ResponseField('migrated_balance', 'integer', 'Unpaid balance of the purchases created up to the cutoff. Frozen once confirmed; computed live (as of now) before that.', example: 5112500)]
    #[ResponseField('migrated_platform_fees', 'integer', 'Their platform fees net of reversals.', example: 1490000)]
    #[ResponseField('migrated_gross_sales', 'integer', 'Total of the confirmed and completed ones.', example: 15000000)]
    #[ResponseField('migrated_refunds', 'integer', 'Refunds on their online payments.', example: 240000)]
    #[ResponseField('balance_adjustment', 'integer', 'Sum of the `baseline_adjustment` ledger entries posted for the center.', example: -4300000)]
    #[ResponseField('platform_fees_delta', 'integer', 'Target minus migrated; null without a target.', nullable: true, example: 30000)]
    #[ResponseField('gross_sales_delta', 'integer', 'Target minus migrated; null without a target.', nullable: true, example: 200000)]
    #[ResponseField('refunds_delta', 'integer', 'Target minus migrated; null without a target.', nullable: true, example: 0)]
    #[ResponseField('note', 'string', 'Where the figures come from.', nullable: true, example: 'Finance reconciliation sheet, November 2026.')]
    #[ResponseField('confirmed_at', 'string', 'Last confirmation (ISO-8601).', nullable: true, example: '2026-11-11T09:30:00+00:00')]
    #[ResponseField('confirmed_by', 'integer', 'System user who confirmed it last.', nullable: true, example: 3)]
    public function toArray(Request $request): array
    {
        $baseline = $this->resource;

        return [
            'center_id' => $baseline->center_id,
            'status' => match (true) {
                ! $baseline->exists => 'not_required',
                $baseline->isConfirmed() => 'confirmed',
                default => 'pending',
            },
            'cutoff_at' => $baseline->cutoff_at?->toIso8601String(),
            'target_balance' => $baseline->target_balance,
            'target_platform_fees' => $baseline->target_platform_fees,
            'target_paid_out' => $baseline->target_paid_out,
            'target_gross_sales' => $baseline->target_gross_sales,
            'target_refunds' => $baseline->target_refunds,
            'migrated_balance' => $baseline->migrated_balance,
            'migrated_platform_fees' => $baseline->migrated_platform_fees,
            'migrated_gross_sales' => $baseline->migrated_gross_sales,
            'migrated_refunds' => $baseline->migrated_refunds,
            'balance_adjustment' => (int) CenterLedgerEntry::query()
                ->where('center_id', $baseline->center_id)
                ->where('type', CenterLedgerEntryType::BaselineAdjustment)
                ->sum('amount'),
            'platform_fees_delta' => $baseline->delta('platform_fees'),
            'gross_sales_delta' => $baseline->delta('gross_sales'),
            'refunds_delta' => $baseline->delta('refunds'),
            'note' => $baseline->note,
            'confirmed_at' => $baseline->confirmed_at?->toIso8601String(),
            'confirmed_by' => $baseline->confirmed_by,
        ];
    }
}
