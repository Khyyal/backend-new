<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Actions\BuildClientActivity;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;

/**
 * Demand: sign-ups, first-time and active buyers, repeat buying and cohort
 * retention. Always platform-wide; center / city slicers do not apply.
 */
class ClientsAnalytics
{
    public function __construct(private readonly RollupReader $reader) {}

    /**
     * @return array<string, mixed>
     */
    public function report(AnalyticsPeriod $period): array
    {
        return [
            'period' => $period->toArray(),
            'kpis' => Kpi::compareAll(
                $this->metrics($period->from, $period->to),
                $period->hasComparison() ? $this->metrics($period->previousFrom, $period->previousTo) : null,
            ),
            'series' => $this->series($period),
            'monthly' => $this->monthly($period),
            'cohorts' => $this->cohorts($period),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = $this->reader->platformTotals($from, $to, ['signups', 'new_buyers']);
        $snapshot = $this->reader->platformSnapshot($to);

        return [
            'signups' => $totals['signups'],
            'new_buyers' => $totals['new_buyers'],
            'signup_conversion_rate' => Kpi::rate($totals['new_buyers'], $totals['signups']),
            'active_buyers_1d' => (int) ($snapshot?->active_buyers_1d ?? 0),
            'active_buyers_7d' => (int) ($snapshot?->active_buyers_7d ?? 0),
            'active_buyers_30d' => (int) ($snapshot?->active_buyers_30d ?? 0),
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function series(AnalyticsPeriod $period): array
    {
        return collect($this->reader->platformSeries($period, ['signups', 'new_buyers'], ['active_buyers_7d', 'active_buyers_30d']))
            ->map(fn (array $row, string $date): array => ['date' => $date, ...$row])
            ->values()->all();
    }

    /**
     * Months overlapping the range, with the repeat buyer rate.
     *
     * @return list<array{month: string, signups: int, buyers: int, new_buyers: int, repeat_buyers: int, repeat_rate: float}>
     */
    private function monthly(AnalyticsPeriod $period): array
    {
        return DB::table('analytics_monthly_client_stats')
            ->whereBetween('month', [$period->from->startOfMonth()->toDateString(), $period->to->toDateString()])
            ->orderBy('month')
            ->get()
            ->map(fn (object $row): array => [
                'month' => substr((string) $row->month, 0, 10),
                'signups' => (int) $row->signups,
                'buyers' => (int) $row->buyers,
                'new_buyers' => (int) $row->new_buyers,
                'repeat_buyers' => (int) $row->repeat_buyers,
                'repeat_rate' => Kpi::rate((int) $row->repeat_buyers, (int) $row->buyers),
            ])->all();
    }

    /**
     * The twelve first-purchase cohorts up to the range end, each with the
     * share of its buyers who bought again N months later.
     *
     * @return list<array{cohort_month: string, cohort_size: int, retention: list<array{months_since: int, active_buyers: int, rate: float}>}>
     */
    private function cohorts(AnalyticsPeriod $period): array
    {
        $last = $period->to->startOfMonth();
        $first = $last->subMonths(BuildClientActivity::MAX_MONTHS_SINCE - 1);

        return DB::table('analytics_cohort_retention')
            ->whereBetween('cohort_month', [$first->toDateString(), $last->toDateString()])
            ->orderBy('cohort_month')
            ->orderBy('months_since')
            ->get()
            ->groupBy(fn (object $row): string => substr((string) $row->cohort_month, 0, 10))
            ->map(fn ($rows, string $cohort): array => [
                'cohort_month' => $cohort,
                'cohort_size' => (int) $rows->first()->cohort_size,
                'retention' => $rows->map(fn (object $row): array => [
                    'months_since' => (int) $row->months_since,
                    'active_buyers' => (int) $row->active_buyers,
                    'rate' => Kpi::rate((int) $row->active_buyers, (int) $row->cohort_size),
                ])->values()->all(),
            ])->values()->all();
    }
}
