<?php

namespace Modules\Homepage\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Homepage\Enums\ContentEntityType;
use Modules\Homepage\Models\Banner;
use Modules\Homepage\Models\HomeSection;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Models\Promotion;
use Modules\Services\Models\Service;

/**
 * Engagement with home content, read from content_daily_stats. Click-through rate is clicks per
 * impression, as a percentage.
 */
class HomeContentAnalytics
{
    private const DEFAULT_DAYS = 30;

    /**
     * @param  array{from_date?: string|null, to_date?: string|null, compare?: string, limit?: int, entity_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $to = CarbonImmutable::parse($filters['to_date'] ?? now()->toDateString());
        $from = CarbonImmutable::parse($filters['from_date'] ?? $to->subDays(self::DEFAULT_DAYS - 1)->toDateString());
        $compare = $filters['compare'] ?? 'prior_period';
        $type = $filters['entity_type'] ?? null;

        $prior = match ($compare) {
            'none' => null,
            'prior_year' => ['from' => $from->subYear(), 'to' => $to->subYear()],
            default => ['from' => $from->subDays($from->diffInDays($to) + 1), 'to' => $from->subDay()],
        };

        $current = $this->totals($from, $to, $type);
        $before = $prior !== null ? $this->totals($prior['from'], $prior['to'], $type) : null;

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'prior' => $prior === null ? null : ['from' => $prior['from']->toDateString(), 'to' => $prior['to']->toDateString()],
            ],
            'kpis' => [
                'impressions' => $this->kpi($current['impressions'], $before['impressions'] ?? null),
                'clicks' => $this->kpi($current['clicks'], $before['clicks'] ?? null),
                'click_through_rate' => $this->kpi($current['ctr'], $before['ctr'] ?? null),
            ],
            'series' => $this->series($from, $to, $type),
            'top_content' => $this->top($from, $to, $type, (int) ($filters['limit'] ?? 10)),
        ];
    }

    private function base(CarbonImmutable $from, CarbonImmutable $to, ?string $type): Builder
    {
        return DB::table('content_daily_stats')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($type !== null, fn (Builder $query) => $query->where('entity_type', $type));
    }

    /**
     * @return array{impressions: int, clicks: int, ctr: float}
     */
    private function totals(CarbonImmutable $from, CarbonImmutable $to, ?string $type): array
    {
        $row = $this->base($from, $to, $type)
            ->selectRaw('COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
            ->first();

        return [
            'impressions' => (int) $row->impressions,
            'clicks' => (int) $row->clicks,
            'ctr' => $this->ctr((int) $row->impressions, (int) $row->clicks),
        ];
    }

    /**
     * @return array{value: int|float, prior: int|float|null, change_percent: float|null}
     */
    private function kpi(int|float $value, int|float|null $prior): array
    {
        return [
            'value' => $value,
            'prior' => $prior,
            'change_percent' => $prior === null || (float) $prior === 0.0 ? null : round(($value - $prior) / $prior * 100, 2),
        ];
    }

    /**
     * One point per day of the period, zero-filled.
     *
     * @return list<array{date: string, impressions: int, clicks: int}>
     */
    private function series(CarbonImmutable $from, CarbonImmutable $to, ?string $type): array
    {
        $rows = $this->base($from, $to, $type)
            ->selectRaw('date, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->groupBy('date')
            ->get()
            ->keyBy(fn (object $row): string => substr((string) $row->date, 0, 10));

        $series = [];

        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $series[] = [
                'date' => $day->toDateString(),
                'impressions' => (int) ($row->impressions ?? 0),
                'clicks' => (int) ($row->clicks ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{entity_type: string, entity_id: int, label: string|null, impressions: int, clicks: int, click_through_rate: float}>
     */
    private function top(CarbonImmutable $from, CarbonImmutable $to, ?string $type, int $limit): array
    {
        $rows = $this->base($from, $to, $type)
            ->selectRaw('entity_type, entity_id, SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->groupBy('entity_type', 'entity_id')
            ->orderByDesc('clicks')
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get();

        $labels = $this->labels($rows->groupBy('entity_type')->map(fn ($group) => $group->pluck('entity_id')->all())->all());

        return $rows->map(fn (object $row): array => [
            'entity_type' => $row->entity_type,
            'entity_id' => (int) $row->entity_id,
            'label' => $labels[$row->entity_type.':'.$row->entity_id] ?? null,
            'impressions' => (int) $row->impressions,
            'clicks' => (int) $row->clicks,
            'click_through_rate' => $this->ctr((int) $row->impressions, (int) $row->clicks),
        ])->values()->all();
    }

    /**
     * @param  array<string, list<int>>  $idsByType
     * @return array<string, string|null>
     */
    private function labels(array $idsByType): array
    {
        $labels = [];

        foreach ($idsByType as $type => $ids) {
            [$model, $attribute] = match (ContentEntityType::from($type)) {
                ContentEntityType::HomeSection => [HomeSection::class, 'key'],
                ContentEntityType::Banner => [Banner::class, 'title'],
                ContentEntityType::Center => [Center::class, 'name'],
                ContentEntityType::Service => [Service::class, 'name'],
                ContentEntityType::Promotion => [Promotion::class, 'name'],
                ContentEntityType::Campaign => [Campaign::class, 'name'],
            };

            $query = $model::query();

            if (method_exists($query->getModel(), 'bootSoftDeletes')) {
                $query->withTrashed();
            }

            $query->whereKey($ids)->get()->each(function (Model $record) use (&$labels, $type, $attribute): void {
                $labels[$type.':'.$record->getKey()] = $record->{$attribute};
            });
        }

        return $labels;
    }

    private function ctr(int $impressions, int $clicks): float
    {
        return $impressions === 0 ? 0.0 : round($clicks / $impressions * 100, 2);
    }
}
