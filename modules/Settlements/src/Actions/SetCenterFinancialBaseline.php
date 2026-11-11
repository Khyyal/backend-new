<?php

namespace Modules\Settlements\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Events\CenterFinancialBaselineConfirmed;
use Modules\Settlements\Models\CenterFinancialBaseline;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Services\CenterBaselineSnapshot;
use Modules\Support\Models\SystemUser;

/**
 * Sets the figures finance holds for a center and moves its balance to them
 * by appending one `baseline_adjustment` ledger entry for the difference.
 *
 * - The first confirmation freezes `cutoff_at` and the migrated snapshot and
 *   posts `target_balance - migrated_balance`.
 * - Later ones post only `new target_balance - previous target_balance`, so
 *   what happened to the center's purchases since is left alone.
 *
 * A zero difference posts nothing. Confirming lifts the payout block.
 */
class SetCenterFinancialBaseline
{
    public function __construct(private CenterBaselineSnapshot $snapshot) {}

    /**
     * @param  array{cutoff_at?: string|null, target_balance: int, target_platform_fees?: int|null, target_paid_out?: int|null, target_gross_sales?: int|null, target_refunds?: int|null, note: string}  $figures
     */
    public function execute(Center $center, array $figures, SystemUser $by): CenterFinancialBaseline
    {
        return DB::transaction(function () use ($center, $figures, $by): CenterFinancialBaseline {
            Center::query()->whereKey($center->getKey())->lockForUpdate()->firstOrFail();

            $baseline = CenterFinancialBaseline::query()->where('center_id', $center->getKey())->lockForUpdate()->first()
                ?? new CenterFinancialBaseline(['center_id' => $center->getKey()]);

            $targetBalance = (int) $figures['target_balance'];

            if ($baseline->isConfirmed()) {
                $difference = $targetBalance - $baseline->target_balance;
            } else {
                $cutoff = Carbon::parse($figures['cutoff_at'] ?? now());
                $baseline->fill(['cutoff_at' => $cutoff, ...$this->snapshot->for($center, $cutoff)]);
                $difference = $targetBalance - $baseline->migrated_balance;
            }

            $baseline->fill([
                'target_balance' => $targetBalance,
                'target_platform_fees' => $figures['target_platform_fees'] ?? null,
                'target_paid_out' => $figures['target_paid_out'] ?? null,
                'target_gross_sales' => $figures['target_gross_sales'] ?? null,
                'target_refunds' => $figures['target_refunds'] ?? null,
                'note' => $figures['note'],
                'confirmed_at' => now(),
                'confirmed_by' => $by->getKey(),
            ])->save();

            $this->post($center, $difference);

            DB::afterCommit(static function () use ($baseline): void {
                event(new CenterFinancialBaselineConfirmed($baseline));
            });

            return $baseline;
        });
    }

    private function post(Center $center, int $difference): void
    {
        if ($difference === 0) {
            return;
        }

        $sequence = CenterLedgerEntry::query()
            ->where('center_id', $center->getKey())
            ->where('type', CenterLedgerEntryType::BaselineAdjustment)
            ->count() + 1;

        CenterLedgerEntry::query()->create([
            'center_id' => $center->getKey(),
            'purchase_id' => null,
            'payment_id' => null,
            'type' => CenterLedgerEntryType::BaselineAdjustment,
            'amount' => $difference,
            'available_at' => now(),
            'idempotency_key' => "baseline:{$center->getKey()}:{$sequence}",
        ]);
    }
}
