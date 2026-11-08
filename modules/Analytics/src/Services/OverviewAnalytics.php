<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;

/**
 * The headline tiles of the analytics home. Active buyers and active centers
 * are trailing-30-day snapshots at the end of each range and stay
 * platform-wide when sliced; new buyers become "new to the center(s)".
 */
class OverviewAnalytics
{
    public function __construct(
        private readonly RollupReader $reader,
        private readonly RevenueAnalytics $revenue,
    ) {}

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
            'series' => $this->revenue->series($period),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $money = $this->revenue->metrics($period, $from, $to);
        $activity = $this->reader->centerTotals($period, $from, $to, [
            'new_buyers', 'reservations_scheduled', 'reservations_cancelled', 'payments_attempted', 'payments_succeeded',
        ], RollupReader::ALL);
        $snapshot = $this->reader->platformSnapshot($to);

        return [
            'gmv' => $money['gmv'],
            'platform_net_revenue' => $money['platform_net_revenue'],
            'take_rate' => $money['take_rate'],
            'purchases_count' => $money['purchases_count'],
            'average_order_value' => $money['average_order_value'],
            'new_buyers' => $period->isSliced()
                ? $activity['new_buyers']
                : $this->reader->platformTotals($from, $to, ['new_buyers'])['new_buyers'],
            'active_buyers_30d' => (int) ($snapshot?->active_buyers_30d ?? 0),
            'active_centers_30d' => (int) ($snapshot?->active_centers_30d ?? 0),
            'cancellation_rate' => Kpi::rate($activity['reservations_cancelled'], $activity['reservations_scheduled']),
            'payment_success_rate' => Kpi::rate($activity['payments_succeeded'], $activity['payments_attempted']),
        ];
    }
}
