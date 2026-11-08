<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Enums\CenterEngagementAction;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Centers\Models\Center;
use Modules\Services\Models\Service;

/**
 * Client engagement with centers (center, service, map and WhatsApp clicks),
 * read from analytics_daily_center_engagement.
 */
class EngagementAnalytics
{
    /**
     * @return array{
     *     period: array<string, mixed>,
     *     kpis: array<string, array{value: int|float, previous: int|float|null, growth: float|null}>,
     *     series: list<array<string, int|string>>,
     *     top_centers: list<array<string, mixed>>,
     *     top_services: list<array<string, mixed>>,
     * }
     */
    public function report(AnalyticsPeriod $period, int $limit = 10): array
    {
        $previous = $period->hasComparison() ? $this->totals($period, $period->previousFrom, $period->previousTo) : null;

        return [
            'period' => $period->toArray(),
            'kpis' => Kpi::compareAll($this->totals($period, $period->from, $period->to), $previous),
            'series' => $this->series($period),
            'top_centers' => $this->topCenters($period, $limit),
            'top_services' => $this->topServices($period, $limit),
        ];
    }

    /**
     * One row per series bucket, zero-filled.
     *
     * @return list<array<string, int|string>>
     */
    public function series(AnalyticsPeriod $period): array
    {
        $columns = CenterEngagementAction::columns();
        $series = array_fill_keys($period->buckets(), array_fill_keys($columns, 0));

        $rows = $this->base($period, $period->from, $period->to)
            ->selectRaw('e.date as date, '.$this->sums($columns))
            ->groupBy('e.date')
            ->get();

        foreach ($rows as $row) {
            $bucket = $period->bucketOf(CarbonImmutable::parse($row->date));

            foreach ($columns as $column) {
                $series[$bucket][$column] += (int) $row->{$column};
            }
        }

        return collect($series)
            ->map(fn (array $row, string $date): array => ['date' => $date, ...$row])
            ->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function totals(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $columns = CenterEngagementAction::columns();
        $row = $this->base($period, $from, $to)->selectRaw($this->sums($columns))->first();

        return collect($columns)->mapWithKeys(fn (string $column): array => [$column => (int) ($row->{$column} ?? 0)])->all();
    }

    /**
     * Centers ranked by all their clicks in the range.
     *
     * @return list<array<string, mixed>>
     */
    private function topCenters(AnalyticsPeriod $period, int $limit): array
    {
        $columns = CenterEngagementAction::columns();

        $rows = $this->base($period, $period->from, $period->to)
            ->selectRaw('e.center_id, '.$this->sums($columns).', SUM('.implode(' + ', array_map(fn (string $column): string => 'e.'.$column, $columns)).') as total_clicks')
            ->groupBy('e.center_id')
            ->orderByDesc('total_clicks')
            ->orderBy('e.center_id')
            ->limit($limit)
            ->get();

        $centers = Center::withTrashed()->whereIn('id', $rows->pluck('center_id')->all())->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'center_id' => (int) $row->center_id,
            'name' => $centers->get($row->center_id)?->name,
            'total_clicks' => (int) $row->total_clicks,
            ...collect($columns)->mapWithKeys(fn (string $column): array => [$column => (int) $row->{$column}])->all(),
        ])->values()->all();
    }

    /**
     * Services ranked by their clicks in the range.
     *
     * @return list<array{service_id: int, name: string|null, center_id: int, center_name: string|null, service_clicks: int}>
     */
    private function topServices(AnalyticsPeriod $period, int $limit): array
    {
        $rows = $this->base($period, $period->from, $period->to)
            ->where('e.service_id', '>', 0)
            ->selectRaw('e.service_id, e.center_id, SUM(e.service_clicks) as service_clicks')
            ->groupBy('e.service_id', 'e.center_id')
            ->orderByDesc('service_clicks')
            ->orderBy('e.service_id')
            ->limit($limit)
            ->get();

        $services = Service::withTrashed()->whereIn('id', $rows->pluck('service_id')->all())->get(['id', 'name'])->keyBy('id');
        $centers = Center::withTrashed()->whereIn('id', $rows->pluck('center_id')->all())->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'service_id' => (int) $row->service_id,
            'name' => $services->get($row->service_id)?->name,
            'center_id' => (int) $row->center_id,
            'center_name' => $centers->get($row->center_id)?->name,
            'service_clicks' => (int) $row->service_clicks,
        ])->values()->all();
    }

    private function base(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('analytics_daily_center_engagement as e')
            ->whereBetween('e.date', [$from->toDateString(), $to->toDateString()])
            ->when($period->centerId !== null, fn (Builder $query) => $query->where('e.center_id', $period->centerId))
            ->when($period->cityId !== null, fn (Builder $query) => $query->whereIn(
                'e.center_id',
                DB::table('centers')->select('id')->where('city_id', $period->cityId),
            ));
    }

    /**
     * @param  list<string>  $columns
     */
    private function sums(array $columns): string
    {
        return implode(', ', array_map(fn (string $column): string => 'COALESCE(SUM(e.'.$column.'), 0) as '.$column, $columns));
    }
}
