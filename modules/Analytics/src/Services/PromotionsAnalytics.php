<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Modules\Analytics\Enums\BreakdownDimension;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;

/**
 * Promotion effectiveness: redemptions still applied on purchases made in
 * the range, the discount given and the GMV of those purchases.
 */
class PromotionsAnalytics
{
    private const TOP_LIMIT = 10;

    public function __construct(private readonly RollupReader $reader) {}

    /**
     * @return array<string, mixed>
     */
    public function report(AnalyticsPeriod $period): array
    {
        return [
            'period' => $period->toArray(),
            'kpis' => Kpi::compareAll(
                $this->metrics($period, $period->from, $period->to),
                $period->hasComparison() ? $this->metrics($period, $period->previousFrom, $period->previousTo) : null,
            ),
            'series' => $this->series($period),
            'top_promotions' => $this->top($period, BreakdownDimension::Promotion),
            'top_coupons' => $this->top($period, BreakdownDimension::Coupon),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $promotions = $this->reader->breakdown($period, BreakdownDimension::Promotion, $from, $to, RollupReader::ALL);
        $sales = $this->reader->centerTotals($period, $from, $to, ['gmv', 'purchases_count'], RollupReader::ALL);

        return [
            'redemptions' => $promotions->sum('count'),
            'discount_given' => $promotions->sum('amount'),
            'promoted_gmv' => $promotions->sum('related_amount'),
            'promoted_gmv_share' => Kpi::rate($promotions->sum('related_amount'), $sales['gmv']),
            'redemption_rate' => Kpi::rate($promotions->sum('count'), $sales['purchases_count']),
        ];
    }

    /**
     * @return list<array{date: string, redemptions: int, discount_given: int}>
     */
    public function series(AnalyticsPeriod $period): array
    {
        return collect($this->reader->breakdownSeries($period, BreakdownDimension::Promotion))
            ->map(fn (array $row, string $date): array => ['date' => $date, 'redemptions' => $row['count'], 'discount_given' => $row['amount']])
            ->values()->all();
    }

    /**
     * @return list<array{id: int, name: string|null, redemptions: int, discount_given: int, promoted_gmv: int}>
     */
    private function top(AnalyticsPeriod $period, BreakdownDimension $dimension): array
    {
        $rows = $this->reader->breakdown($period, $dimension, $period->from, $period->to, RollupReader::ALL)
            ->sortByDesc('count')
            ->take(self::TOP_LIMIT);

        $names = $dimension === BreakdownDimension::Promotion
            ? Promotion::withTrashed()->whereIn('id', $rows->keys()->all())->get()->mapWithKeys(fn (Promotion $promotion): array => [$promotion->id => $promotion->name])
            : Coupon::query()->whereIn('id', $rows->keys()->all())->pluck('code', 'id');

        return $rows->map(fn (array $row, string $id): array => [
            'id' => (int) $id,
            'name' => $names->get((int) $id),
            'redemptions' => $row['count'],
            'discount_given' => $row['amount'],
            'promoted_gmv' => $row['related_amount'],
        ])->values()->all();
    }
}
