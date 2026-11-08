<?php

namespace Modules\Analytics\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Enums\BreakdownDimension;

/**
 * Reads the analytics rollup tables. Center-stat reads honour the period's
 * center / city slicers; platform-stat reads are platform-wide.
 */
class RollupReader
{
    /** Center merchants only (the marketplace). */
    public const CENTERS = 'centers';

    /** The platform merchant only (subscriptions and memberships it sells). */
    public const PLATFORM = 'platform';

    /** Both. */
    public const ALL = 'all';

    /**
     * Sums of center-stat columns over a date range.
     *
     * @param  list<string>  $columns
     * @return array<string, int>
     */
    public function centerTotals(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to, array $columns, string $merchants = self::CENTERS): array
    {
        $row = $this->centerStats($period, $merchants)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select(array_map(fn (string $column) => DB::raw("coalesce(sum({$column}), 0) as {$column}"), $columns))
            ->first();

        return collect($columns)->mapWithKeys(fn (string $column): array => [$column => (int) ($row->{$column} ?? 0)])->all();
    }

    /**
     * Center-stat column sums per series bucket of the period, zero-filled.
     *
     * @param  list<string>  $columns
     * @return array<string, array<string, int>> bucket start => column => sum
     */
    public function centerSeries(AnalyticsPeriod $period, array $columns, string $merchants = self::CENTERS): array
    {
        $rows = $this->centerStats($period, $merchants)
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->select('date')
            ->addSelect(array_map(fn (string $column) => DB::raw("coalesce(sum({$column}), 0) as {$column}"), $columns))
            ->groupBy('date')
            ->get();

        return $this->bucket($period, $rows, $columns);
    }

    /**
     * Per-center sums over a date range, keyed by center id (platform excluded).
     *
     * @param  list<string>  $columns
     * @return Collection<int, array<string, int>>
     */
    public function perCenter(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to, array $columns): Collection
    {
        return $this->centerStats($period, self::CENTERS)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('center_id')
            ->addSelect(array_map(fn (string $column) => DB::raw("coalesce(sum({$column}), 0) as {$column}"), $columns))
            ->groupBy('center_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->center_id => collect($columns)->mapWithKeys(fn (string $column): array => [$column => (int) $row->{$column}])->all(),
            ]);
    }

    /**
     * The last date each center had a counted purchase, up to `to`.
     *
     * @return Collection<int, string>
     */
    public function lastSaleDates(AnalyticsPeriod $period, CarbonImmutable $to): Collection
    {
        return $this->centerStats($period, self::CENTERS)
            ->where('date', '<=', $to->toDateString())
            ->where('purchases_count', '>', 0)
            ->select('center_id')
            ->selectRaw('max(date) as last_sale')
            ->groupBy('center_id')
            ->pluck('last_sale', 'center_id')
            ->mapWithKeys(fn (mixed $date, int|string $centerId): array => [(int) $centerId => substr((string) $date, 0, 10)]);
    }

    /**
     * Center-stat sums over a range grouped by city id (null = no city).
     *
     * @param  list<string>  $columns
     * @return Collection<int, object>
     */
    public function perCity(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to, array $columns): Collection
    {
        return $this->centerStats($period, self::CENTERS)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('city_id')
            ->addSelect(array_map(fn (string $column) => DB::raw("coalesce(sum({$column}), 0) as {$column}"), $columns))
            ->groupBy('city_id')
            ->get();
    }

    /**
     * Breakdown sums over a range, keyed by dimension key, largest amount first.
     *
     * @return Collection<string, array{count: int, success_count: int, amount: int, related_amount: int}>
     */
    public function breakdown(AnalyticsPeriod $period, BreakdownDimension $dimension, CarbonImmutable $from, CarbonImmutable $to, string $merchants = self::CENTERS): Collection
    {
        return $this->sliced(DB::table('analytics_daily_breakdowns'), $period, $merchants)
            ->where('dimension', $dimension->value)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('dimension_key')
            ->selectRaw('coalesce(sum(count), 0) as count')
            ->selectRaw('coalesce(sum(success_count), 0) as success_count')
            ->selectRaw('coalesce(sum(amount), 0) as amount')
            ->selectRaw('coalesce(sum(related_amount), 0) as related_amount')
            ->groupBy('dimension_key')
            ->orderByDesc('amount')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->dimension_key => [
                'count' => (int) $row->count,
                'success_count' => (int) $row->success_count,
                'amount' => (int) $row->amount,
                'related_amount' => (int) $row->related_amount,
            ]]);
    }

    /**
     * Breakdown column sums per series bucket of the period, zero-filled.
     *
     * @return array<string, array{count: int, amount: int}>
     */
    public function breakdownSeries(AnalyticsPeriod $period, BreakdownDimension $dimension, string $merchants = self::ALL): array
    {
        $rows = $this->sliced(DB::table('analytics_daily_breakdowns'), $period, $merchants)
            ->where('dimension', $dimension->value)
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->select('date')
            ->selectRaw('coalesce(sum(count), 0) as count')
            ->selectRaw('coalesce(sum(amount), 0) as amount')
            ->groupBy('date')
            ->get();

        return $this->bucket($period, $rows, ['count', 'amount']);
    }

    /**
     * Sums of platform-stat columns over a range.
     *
     * @param  list<string>  $columns
     * @return array<string, int>
     */
    public function platformTotals(CarbonImmutable $from, CarbonImmutable $to, array $columns): array
    {
        $row = DB::table('analytics_daily_platform_stats')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select(array_map(fn (string $column) => DB::raw("coalesce(sum({$column}), 0) as {$column}"), $columns))
            ->first();

        return collect($columns)->mapWithKeys(fn (string $column): array => [$column => (int) ($row->{$column} ?? 0)])->all();
    }

    /**
     * The platform-stat row of the latest built day on or before the date.
     */
    public function platformSnapshot(CarbonImmutable $date): ?object
    {
        return DB::table('analytics_daily_platform_stats')
            ->where('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->first();
    }

    /**
     * The latest platform-stat row that has the center-state and payout
     * snapshots (only days built as "yesterday" do).
     */
    public function latestSnapshot(): ?object
    {
        return DB::table('analytics_daily_platform_stats')
            ->whereNotNull('centers_active')
            ->orderByDesc('date')
            ->first();
    }

    /**
     * Platform-stat series: `sum` columns are summed per bucket, `last`
     * columns take the value of the bucket's last day (snapshots).
     *
     * @param  list<string>  $sum
     * @param  list<string>  $last
     * @return array<string, array<string, int>>
     */
    public function platformSeries(AnalyticsPeriod $period, array $sum, array $last = []): array
    {
        $rows = DB::table('analytics_daily_platform_stats')
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->orderBy('date')
            ->get();

        $series = $this->bucket($period, $rows, $sum);

        foreach ($rows as $row) {
            $bucket = $period->bucketOf(CarbonImmutable::parse($row->date));

            foreach ($last as $column) {
                $series[$bucket][$column] = (int) $row->{$column};
            }
        }

        foreach ($series as $bucket => $values) {
            foreach ($last as $column) {
                $series[$bucket][$column] ??= 0;
            }
        }

        return $series;
    }

    /**
     * When the data was last built: the latest built day and the build time.
     *
     * @return array{date: string|null, built_at: string|null}
     */
    public function dataAsOf(): array
    {
        $row = DB::table('analytics_daily_platform_stats')->orderByDesc('date')->first(['date', 'built_at']);

        return [
            'date' => $row === null ? null : substr((string) $row->date, 0, 10),
            'built_at' => $row === null ? null : CarbonImmutable::parse($row->built_at)->toIso8601String(),
        ];
    }

    private function centerStats(AnalyticsPeriod $period, string $merchants): Builder
    {
        return $this->sliced(DB::table('analytics_daily_center_stats'), $period, $merchants);
    }

    private function sliced(Builder $query, AnalyticsPeriod $period, string $merchants): Builder
    {
        return $query
            ->when($merchants === self::CENTERS, fn (Builder $query) => $query->where('center_id', '>', 0))
            ->when($merchants === self::PLATFORM, fn (Builder $query) => $query->where('center_id', 0))
            ->when($period->centerId !== null, fn (Builder $query) => $query->where('center_id', $period->centerId))
            ->when($period->cityId !== null, fn (Builder $query) => $query->where('city_id', $period->cityId));
    }

    /**
     * @param  Collection<int, object>  $rows  Daily rows with a `date`.
     * @param  list<string>  $columns
     * @return array<string, array<string, int>>
     */
    private function bucket(AnalyticsPeriod $period, Collection $rows, array $columns): array
    {
        $zero = array_fill_keys($columns, 0);
        $series = array_fill_keys($period->buckets(), $zero);

        foreach ($rows as $row) {
            $bucket = $period->bucketOf(CarbonImmutable::parse($row->date));

            foreach ($columns as $column) {
                $series[$bucket][$column] += (int) $row->{$column};
            }
        }

        return $series;
    }
}
