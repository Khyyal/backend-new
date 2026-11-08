<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Modules\Analytics\Enums\BreakdownDimension;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;

/**
 * Reservations: bookings made in the range (by creation date) and the
 * outcome of reservations scheduled in it (by service date).
 */
class BookingsAnalytics
{
    private const COLUMNS = [
        'bookings_made', 'tickets_booked', 'reservations_scheduled', 'reservations_completed',
        'reservations_no_show', 'reservations_cancelled', 'cancellations_by_client',
    ];

    public function __construct(private readonly RollupReader $reader) {}

    /**
     * @return array<string, mixed>
     */
    public function report(AnalyticsPeriod $period): array
    {
        $serviceTypes = $this->reader->breakdown($period, BreakdownDimension::ServiceType, $period->from, $period->to);

        return [
            'period' => $period->toArray(),
            'kpis' => Kpi::compareAll(
                $this->metrics($period, $period->from, $period->to),
                $period->hasComparison() ? $this->metrics($period, $period->previousFrom, $period->previousTo) : null,
            ),
            'series' => $this->series($period),
            'by_service_type' => $serviceTypes->map(fn (array $row, string $key): array => [
                'key' => $key,
                'purchases_count' => $row['count'],
                'gmv' => $row['amount'],
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = $this->reader->centerTotals($period, $from, $to, self::COLUMNS);
        $attended = $totals['reservations_completed'] + $totals['reservations_no_show'];

        return [
            ...$totals,
            'cancellation_rate' => Kpi::rate($totals['reservations_cancelled'], $totals['reservations_scheduled']),
            'client_cancellation_share' => Kpi::rate($totals['cancellations_by_client'], $totals['reservations_cancelled']),
            'no_show_rate' => Kpi::rate($totals['reservations_no_show'], $attended),
        ];
    }

    /**
     * @return list<array<string, int|float|string>>
     */
    public function series(AnalyticsPeriod $period): array
    {
        return collect($this->reader->centerSeries($period, self::COLUMNS))
            ->map(fn (array $row, string $date): array => [
                'date' => $date,
                ...$row,
                'cancellation_rate' => Kpi::rate($row['reservations_cancelled'], $row['reservations_scheduled']),
            ])
            ->values()->all();
    }
}
