<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Modules\Analytics\Enums\BreakdownDimension;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;

/**
 * Checkout health: online payment attempts (each retry is an attempt), how
 * many succeed, how many purchases end up counted, and the payment mix.
 * Covers center and platform merchants.
 */
class PaymentsAnalytics
{
    private const COLUMNS = ['payments_attempted', 'payments_succeeded', 'payments_failed', 'purchases_count', 'all_purchases_count', 'provider_fees'];

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
            'by_method' => $this->mix($period, BreakdownDimension::PaymentMethod),
            'by_company' => $this->mix($period, BreakdownDimension::PaymentCompany),
            'by_type' => $this->mix($period, BreakdownDimension::PaymentType),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = $this->reader->centerTotals($period, $from, $to, self::COLUMNS, RollupReader::ALL);

        return [
            'payments_attempted' => $totals['payments_attempted'],
            'payments_succeeded' => $totals['payments_succeeded'],
            'payments_failed' => $totals['payments_failed'],
            'payment_success_rate' => Kpi::rate($totals['payments_succeeded'], $totals['payments_attempted']),
            'purchases_created' => $totals['all_purchases_count'],
            'purchase_conversion_rate' => Kpi::rate($totals['purchases_count'], $totals['all_purchases_count']),
            'provider_fees' => $totals['provider_fees'],
        ];
    }

    /**
     * @return list<array<string, int|float|string>>
     */
    public function series(AnalyticsPeriod $period): array
    {
        return collect($this->reader->centerSeries($period, self::COLUMNS, RollupReader::ALL))
            ->map(fn (array $row, string $date): array => [
                'date' => $date,
                'payments_attempted' => $row['payments_attempted'],
                'payments_succeeded' => $row['payments_succeeded'],
                'payments_failed' => $row['payments_failed'],
                'payment_success_rate' => Kpi::rate($row['payments_succeeded'], $row['payments_attempted']),
                'purchase_conversion_rate' => Kpi::rate($row['purchases_count'], $row['all_purchases_count']),
            ])
            ->values()->all();
    }

    /**
     * @return list<array{key: string, attempts: int, succeeded: int, success_rate: float, amount: int, share: float}>
     */
    private function mix(AnalyticsPeriod $period, BreakdownDimension $dimension): array
    {
        $rows = $this->reader->breakdown($period, $dimension, $period->from, $period->to, RollupReader::ALL);
        $total = $rows->sum('amount');

        return $rows->map(fn (array $row, string $key): array => [
            'key' => $key,
            'attempts' => $row['count'],
            'succeeded' => $row['success_count'],
            'success_rate' => Kpi::rate($row['success_count'], $row['count']),
            'amount' => $row['amount'],
            'share' => Kpi::rate($row['amount'], $total),
        ])->values()->all();
    }
}
