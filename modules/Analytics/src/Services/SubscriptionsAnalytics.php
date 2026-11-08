<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;

/**
 * Center subscriptions to platform plans: the paying and trialing base, new
 * and cancelled subscriptions, MRR (yearly plans count price / 12) and churn
 * (cancellations / paying subscriptions at the start of the range).
 * Platform-wide; center / city slicers do not apply.
 */
class SubscriptionsAnalytics
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
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $end = $this->reader->platformSnapshot($to);
        $start = $this->reader->platformSnapshot($from->subDay());
        $flows = $this->reader->platformTotals($from, $to, ['subscriptions_new', 'subscriptions_cancelled']);

        return [
            'subscriptions_active' => (int) ($end?->subscriptions_active ?? 0),
            'subscriptions_trialing' => (int) ($end?->subscriptions_trialing ?? 0),
            'subscriptions_new' => $flows['subscriptions_new'],
            'subscriptions_cancelled' => $flows['subscriptions_cancelled'],
            'mrr' => (int) ($end?->mrr ?? 0),
            'churn_rate' => Kpi::rate($flows['subscriptions_cancelled'], (int) ($start?->subscriptions_active ?? 0)),
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function series(AnalyticsPeriod $period): array
    {
        return collect($this->reader->platformSeries($period, ['subscriptions_new', 'subscriptions_cancelled'], ['subscriptions_active', 'subscriptions_trialing', 'mrr']))
            ->map(fn (array $row, string $date): array => ['date' => $date, ...$row])
            ->values()->all();
    }
}
