<?php

namespace Modules\Promotions\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Promotions\Enums\RedemptionStatus;

/**
 * Safety net for the denormalised usage counters. The redemption ledger is
 * the source of truth: a counter should equal the number of *applied*
 * redemptions it summarises. Run off-peak, because a checkout in flight can
 * make a counter briefly differ from the ledger it is about to write.
 */
class CounterReconciler
{
    /**
     * @return array{promotions: int, coupons: int, promotion_client_usages: int, coupon_client_usages: int} Rows corrected (or that would be, on a dry run).
     */
    public function reconcile(bool $dryRun = false): array
    {
        $fixed = [
            'promotions' => $this->reconcileTotals('promotions', 'usage_count', 'promotion_id', $dryRun),
            'coupons' => $this->reconcileTotals('coupons', 'used_count', 'coupon_id', $dryRun),
            'promotion_client_usages' => $this->reconcileClientUsages('promotion_client_usages', 'promotion_id', 'promotion_id', false, $dryRun),
            'coupon_client_usages' => $this->reconcileClientUsages('coupon_client_usages', 'coupon_id', 'coupon_id', true, $dryRun),
        ];

        if (array_sum($fixed) > 0) {
            Log::warning('Promotion usage counters drifted from the redemption ledger.', $fixed + ['dry_run' => $dryRun]);
        }

        return $fixed;
    }

    private function reconcileTotals(string $table, string $column, string $ledgerColumn, bool $dryRun): int
    {
        $truth = DB::table('promotion_redemptions')
            ->where('status', RedemptionStatus::Applied->value)
            ->whereNotNull($ledgerColumn)
            ->groupBy($ledgerColumn)
            ->pluck(DB::raw('COUNT(*)'), $ledgerColumn);

        $fixed = 0;

        DB::table($table)->select(['id', $column])->orderBy('id')->each(function (object $row) use ($table, $column, $truth, $dryRun, &$fixed): void {
            $expected = (int) $truth->get($row->id, 0);

            if ((int) $row->{$column} === $expected) {
                return;
            }

            $fixed++;

            if (! $dryRun) {
                DB::table($table)->where('id', $row->id)->update([$column => $expected]);
            }
        });

        return $fixed;
    }

    private function reconcileClientUsages(string $table, string $keyColumn, string $ledgerColumn, bool $onlyCoupons, bool $dryRun): int
    {
        $query = DB::table('promotion_redemptions')
            ->where('status', RedemptionStatus::Applied->value)
            ->groupBy($ledgerColumn, 'client_id');

        if ($onlyCoupons) {
            $query->whereNotNull('coupon_id');
        }

        $truth = [];
        foreach ($query->get([$ledgerColumn.' as key_id', 'client_id', DB::raw('COUNT(*) as uses')]) as $row) {
            $truth[$row->key_id.':'.$row->client_id] = (int) $row->uses;
        }

        $fixed = 0;

        foreach (DB::table($table)->orderBy($keyColumn)->orderBy('client_id')->get() as $row) {
            $key = $row->{$keyColumn}.':'.$row->client_id;
            $expected = $truth[$key] ?? 0;
            unset($truth[$key]);

            if ((int) $row->used_count === $expected) {
                continue;
            }

            $fixed++;

            if (! $dryRun) {
                DB::table($table)->where($keyColumn, $row->{$keyColumn})->where('client_id', $row->client_id)->update(['used_count' => $expected]);
            }
        }

        // Ledger entries whose counter row is missing entirely.
        foreach ($truth as $key => $uses) {
            [$id, $clientId] = explode(':', $key);
            $fixed++;

            if (! $dryRun) {
                DB::table($table)->insertOrIgnore([$keyColumn => (int) $id, 'client_id' => (int) $clientId, 'used_count' => $uses]);
            }
        }

        return $fixed;
    }
}
