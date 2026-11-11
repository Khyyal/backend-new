<?php

namespace Modules\Settlements\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Settlements\Enums\CenterLedgerEntryType;

/**
 * What a center's own data says about its purchases created up to a cutoff,
 * for comparison with the figures finance holds.
 *
 * - `migrated_balance`: net of their ledger entries not yet in a payout.
 * - `migrated_platform_fees`: platform fees net of reversals.
 * - `migrated_refunds`: refunds on online payments.
 * - `migrated_gross_sales`: totals of confirmed and completed purchases, the
 *   same rule as analytics GMV.
 */
class CenterBaselineSnapshot
{
    /**
     * @return array{migrated_balance: int, migrated_platform_fees: int, migrated_gross_sales: int, migrated_refunds: int}
     */
    public function for(Center $center, CarbonInterface $cutoff): array
    {
        $feeTypes = array_column(CenterLedgerEntryType::feeTypes(), 'value');
        $placeholders = implode(',', array_fill(0, count($feeTypes), '?'));

        $ledger = DB::table('center_ledger_entries')
            ->join('purchases', 'purchases.id', '=', 'center_ledger_entries.purchase_id')
            ->where('center_ledger_entries.center_id', $center->getKey())
            ->where('purchases.created_at', '<=', $cutoff)
            ->selectRaw('coalesce(sum(case when center_ledger_entries.payout_id is null then center_ledger_entries.amount else 0 end), 0) as balance')
            ->selectRaw("coalesce(sum(case when center_ledger_entries.type in ({$placeholders}) then center_ledger_entries.amount else 0 end), 0) as fees", $feeTypes)
            ->selectRaw('coalesce(sum(case when center_ledger_entries.type = ? then center_ledger_entries.amount else 0 end), 0) as refunds', [CenterLedgerEntryType::Refund->value])
            ->first();

        $grossSales = DB::table('purchases')
            ->where('merchant_type', $center->getMorphClass())
            ->where('merchant_id', $center->getKey())
            ->whereNull('deleted_at')
            ->whereIn('status', [PurchaseStatus::Confirmed->value, PurchaseStatus::Completed->value])
            ->where('created_at', '<=', $cutoff)
            ->sum('total_amount');

        return [
            'migrated_balance' => (int) $ledger->balance,
            'migrated_platform_fees' => -(int) $ledger->fees,
            'migrated_gross_sales' => (int) $grossSales,
            'migrated_refunds' => -(int) $ledger->refunds,
        ];
    }
}
