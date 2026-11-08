<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Models\Center;

/**
 * Supply: how many centers there are and sell, which ones lead, which ones
 * are slipping, and what the platform owes them.
 */
class CentersAnalytics
{
    public const SORTS = ['gmv', 'purchases_count', 'net_fee'];

    private const COLUMNS = ['gmv', 'purchases_count', 'net_fee', 'reservations_scheduled', 'reservations_cancelled', 'ratings_count', 'ratings_stars_sum'];

    public function __construct(private readonly RollupReader $reader) {}

    /**
     * @return array<string, mixed>
     */
    public function report(AnalyticsPeriod $period, string $sort = 'gmv', int $limit = 10): array
    {
        $current = $this->reader->perCenter($period, $period->from, $period->to, self::COLUMNS);
        $previous = $period->hasComparison()
            ? $this->reader->perCenter($period, $period->previousFrom, $period->previousTo, ['gmv'])
            : collect();

        $snapshot = $this->reader->latestSnapshot();

        return [
            'period' => $period->toArray(),
            'kpis' => Kpi::compareAll(
                $this->metrics($period->from, $period->to),
                $period->hasComparison() ? $this->metrics($period->previousFrom, $period->previousTo) : null,
            ),
            'states' => [
                'active' => $snapshot?->centers_active === null ? null : (int) $snapshot->centers_active,
                'pending' => $snapshot?->centers_pending === null ? null : (int) $snapshot->centers_pending,
                'blocked' => $snapshot?->centers_blocked === null ? null : (int) $snapshot->centers_blocked,
            ],
            'payout_liability' => [
                'unpaid_balance' => $snapshot?->payout_unpaid_balance === null ? null : (int) $snapshot->payout_unpaid_balance,
                'on_hold' => $snapshot?->payout_on_hold === null ? null : (int) $snapshot->payout_on_hold,
                'pending_payouts' => $snapshot?->payout_pending === null ? null : (int) $snapshot->payout_pending,
                'as_of' => $snapshot === null ? null : substr((string) $snapshot->date, 0, 10),
            ],
            'series' => $this->series($period),
            'leaderboard' => $this->leaderboard($current, $period->hasComparison() ? $previous : null, $sort, $limit),
            'at_risk' => $this->atRisk($period, $current, $previous),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function metrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $snapshot = $this->reader->platformSnapshot($to);

        return [
            'centers_total' => (int) ($snapshot?->centers_total ?? 0),
            'new_centers' => $this->reader->platformTotals($from, $to, ['new_centers'])['new_centers'],
            'active_centers_30d' => (int) ($snapshot?->active_centers_30d ?? 0),
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function series(AnalyticsPeriod $period): array
    {
        return collect($this->reader->platformSeries($period, ['new_centers'], ['centers_total', 'active_centers_30d']))
            ->map(fn (array $row, string $date): array => ['date' => $date, ...$row])
            ->values()->all();
    }

    /**
     * @param  Collection<int, array<string, int>>  $current
     * @param  Collection<int, array<string, int>>|null  $previous  Null without a comparison.
     * @return list<array<string, mixed>>
     */
    private function leaderboard(Collection $current, ?Collection $previous, string $sort, int $limit): array
    {
        $ranked = $current->sortByDesc(fn (array $row): int => $row[$sort])->take($limit);
        $centers = $this->centers($ranked->keys());

        return $ranked->map(fn (array $row, int $centerId): array => [
            'center_id' => $centerId,
            'name' => $centers->get($centerId)?->name,
            'state' => $centers->get($centerId)?->state?->value,
            'gmv' => Kpi::compare($row['gmv'], $previous === null ? null : ($previous->get($centerId)['gmv'] ?? 0)),
            'purchases_count' => $row['purchases_count'],
            'net_fees' => $row['net_fee'],
            'take_rate' => Kpi::rate($row['net_fee'], $row['gmv']),
            'cancellation_rate' => Kpi::rate($row['reservations_cancelled'], $row['reservations_scheduled']),
            'ratings_count' => $row['ratings_count'],
            'average_rating' => $row['ratings_count'] === 0 ? null : round($row['ratings_stars_sum'] / $row['ratings_count'], 2),
        ])->values()->all();
    }

    /**
     * Active centers whose GMV fell by at least `analytics.at_risk_drop_percent`
     * against the previous period, or that have sold before but not for
     * `analytics.at_risk_idle_days` days by the range end.
     *
     * @param  Collection<int, array<string, int>>  $current
     * @param  Collection<int, array<string, int>>  $previous
     * @return list<array{center_id: int, name: string|null, reason: string, gmv: int, previous_gmv: int|null, last_sale_on: string|null}>
     */
    private function atRisk(AnalyticsPeriod $period, Collection $current, Collection $previous): array
    {
        $dropPercent = (int) config('analytics.at_risk_drop_percent');
        $idleSince = $period->to->subDays((int) config('analytics.at_risk_idle_days'))->toDateString();
        $lastSales = $this->reader->lastSaleDates($period, $period->to);

        $candidates = $previous->keys()->merge($lastSales->keys())->unique();
        $centers = $this->centers($candidates)->filter(fn (Center $center): bool => $center->state === CenterState::Active);

        return $centers->map(function (Center $center) use ($current, $previous, $lastSales, $dropPercent, $idleSince): ?array {
            $gmv = $current->get($center->id)['gmv'] ?? 0;
            $previousGmv = $previous->get($center->id)['gmv'] ?? null;
            $lastSale = $lastSales->get($center->id);

            $reason = match (true) {
                $lastSale !== null && $lastSale < $idleSince => 'idle',
                $previousGmv !== null && $previousGmv > 0 && $gmv * 100 <= $previousGmv * (100 - $dropPercent) => 'gmv_drop',
                default => null,
            };

            return $reason === null ? null : [
                'center_id' => $center->id,
                'name' => $center->name,
                'reason' => $reason,
                'gmv' => $gmv,
                'previous_gmv' => $previousGmv,
                'last_sale_on' => $lastSale,
            ];
        })
            ->filter()
            ->sortByDesc(fn (array $row): int => $row['previous_gmv'] ?? 0)
            ->take(50)
            ->values()->all();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Center>
     */
    private function centers(Collection $ids): Collection
    {
        return Center::withTrashed()->whereIn('id', $ids->all())->get(['id', 'name', 'state'])->keyBy('id');
    }
}
