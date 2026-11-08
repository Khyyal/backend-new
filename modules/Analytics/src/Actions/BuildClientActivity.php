<?php

namespace Modules\Analytics\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Support\MerchantScope;

/**
 * Rebuilds analytics_monthly_client_stats and analytics_cohort_retention in
 * full from every counted client purchase. Months are computed in PHP so the
 * build stays database-agnostic.
 *
 * - buyers: clients with a counted purchase in the month.
 * - new_buyers: of those, the ones whose first counted purchase is in the month.
 * - repeat_buyers: of those, the ones with two or more counted purchases up
 *   to the end of the month.
 * - cohort retention: of the clients whose first purchase is in a month, how
 *   many bought again N months later (N = 0..MAX_MONTHS_SINCE).
 */
class BuildClientActivity
{
    public const MAX_MONTHS_SINCE = 12;

    public function execute(): void
    {
        /** @var array<int, string> $firstMonth */
        $firstMonth = [];
        /** @var array<int, int> $purchaseCount */
        $purchaseCount = [];
        /** @var array<string, array<int, int>> $buyersByMonth month => buyer => purchases up to the month's end */
        $buyersByMonth = [];

        $purchases = MerchantScope::counted(DB::table('purchases'))
            ->where('buyer_type', MerchantScope::clientMorph())
            ->orderBy('created_at')
            ->orderBy('id')
            ->select(['id', 'buyer_id', 'created_at'])
            ->lazy(2000);

        foreach ($purchases as $purchase) {
            $buyer = (int) $purchase->buyer_id;
            $month = substr((string) $purchase->created_at, 0, 7).'-01';

            $firstMonth[$buyer] ??= $month;
            $purchaseCount[$buyer] = ($purchaseCount[$buyer] ?? 0) + 1;
            $buyersByMonth[$month][$buyer] = $purchaseCount[$buyer];
        }

        $signups = DB::table('clients')->pluck('created_at')
            ->countBy(fn (mixed $createdAt): string => substr((string) $createdAt, 0, 7).'-01');

        $builtAt = now();
        $months = collect(array_keys($buyersByMonth))->merge($signups->keys())->unique()->sort()->values();

        $monthly = $months->map(fn (string $month): array => [
            'month' => $month,
            'signups' => (int) $signups->get($month, 0),
            'buyers' => count($buyersByMonth[$month] ?? []),
            'new_buyers' => collect(array_keys($buyersByMonth[$month] ?? []))->filter(fn (int $buyer): bool => $firstMonth[$buyer] === $month)->count(),
            'repeat_buyers' => collect($buyersByMonth[$month] ?? [])->filter(fn (int $count): bool => $count >= 2)->count(),
            'built_at' => $builtAt,
        ]);

        $cohorts = $this->cohorts($firstMonth, $buyersByMonth, $builtAt);

        DB::transaction(function () use ($monthly, $cohorts): void {
            DB::table('analytics_monthly_client_stats')->delete();
            DB::table('analytics_cohort_retention')->delete();

            $monthly->chunk(500)->each(fn ($chunk) => DB::table('analytics_monthly_client_stats')->insert($chunk->values()->all()));
            collect($cohorts)->chunk(500)->each(fn ($chunk) => DB::table('analytics_cohort_retention')->insert($chunk->values()->all()));
        });
    }

    /**
     * @param  array<int, string>  $firstMonth
     * @param  array<string, array<int, int>>  $buyersByMonth
     * @return list<array{cohort_month: string, months_since: int, cohort_size: int, active_buyers: int, built_at: mixed}>
     */
    private function cohorts(array $firstMonth, array $buyersByMonth, mixed $builtAt): array
    {
        $members = [];

        foreach ($firstMonth as $buyer => $month) {
            $members[$month][] = $buyer;
        }

        $lastMonth = array_key_last($buyersByMonth) ?? null;
        $rows = [];

        foreach ($members as $cohort => $buyers) {
            for ($since = 0; $since <= self::MAX_MONTHS_SINCE; $since++) {
                $month = date('Y-m-01', strtotime("{$cohort} +{$since} months"));

                if ($lastMonth === null || $month > $lastMonth) {
                    break;
                }

                $active = $buyersByMonth[$month] ?? [];

                $rows[] = [
                    'cohort_month' => $cohort,
                    'months_since' => $since,
                    'cohort_size' => count($buyers),
                    'active_buyers' => count(array_filter($buyers, fn (int $buyer): bool => isset($active[$buyer]))),
                    'built_at' => $builtAt,
                ];
            }
        }

        return $rows;
    }
}
