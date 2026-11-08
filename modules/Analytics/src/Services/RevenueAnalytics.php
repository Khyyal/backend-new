<?php

namespace Modules\Analytics\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Analytics\Enums\BreakdownDimension;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\Kpi;
use Modules\Analytics\Support\RollupReader;
use Modules\Support\Models\City;

/**
 * Money: what flows through the marketplace (GMV) and what the platform keeps.
 *
 * platform_net_revenue = net fees + platform sales - platform-funded subsidies - provider fees.
 * Platform sales (subscriptions, memberships) are left out when the request
 * is sliced by center or city.
 */
class RevenueAnalytics
{
    private const CENTER_COLUMNS = ['gmv', 'purchases_count', 'net_fee', 'subsidy', 'refunds', 'discount_platform', 'discount_center', 'provider_fees'];

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
            'by_channel' => $this->shares($this->reader->breakdown($period, BreakdownDimension::Channel, $period->from, $period->to)),
            'by_service_type' => $this->shares($this->reader->breakdown($period, BreakdownDimension::ServiceType, $period->from, $period->to)),
            'by_city' => $this->byCity($period),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function metrics(AnalyticsPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $centers = $this->reader->centerTotals($period, $from, $to, self::CENTER_COLUMNS);
        $platform = $period->isSliced()
            ? ['gmv' => 0, 'provider_fees' => 0]
            : $this->reader->centerTotals($period, $from, $to, ['gmv', 'provider_fees'], RollupReader::PLATFORM);

        $providerFees = $centers['provider_fees'] + $platform['provider_fees'];

        return [
            'gmv' => $centers['gmv'],
            'purchases_count' => $centers['purchases_count'],
            'average_order_value' => Kpi::average($centers['gmv'], $centers['purchases_count']),
            'net_fees' => $centers['net_fee'],
            'take_rate' => Kpi::rate($centers['net_fee'], $centers['gmv']),
            'platform_sales' => $platform['gmv'],
            'platform_subsidies' => $centers['subsidy'],
            'provider_fees' => $providerFees,
            'platform_net_revenue' => $centers['net_fee'] + $platform['gmv'] - $centers['subsidy'] - $providerFees,
            'discounts_platform_funded' => $centers['discount_platform'],
            'discounts_center_funded' => $centers['discount_center'],
            'refunds' => $centers['refunds'],
            'refund_rate' => Kpi::rate($centers['refunds'], $centers['gmv']),
        ];
    }

    /**
     * @return list<array<string, int|float|string>>
     */
    public function series(AnalyticsPeriod $period): array
    {
        $centers = $this->reader->centerSeries($period, self::CENTER_COLUMNS);
        $platform = $period->isSliced() ? [] : $this->reader->centerSeries($period, ['gmv', 'provider_fees'], RollupReader::PLATFORM);

        return collect($centers)->map(function (array $row, string $date) use ($platform): array {
            $platformSales = $platform[$date]['gmv'] ?? 0;
            $providerFees = $row['provider_fees'] + ($platform[$date]['provider_fees'] ?? 0);

            return [
                'date' => $date,
                'gmv' => $row['gmv'],
                'purchases_count' => $row['purchases_count'],
                'net_fees' => $row['net_fee'],
                'platform_sales' => $platformSales,
                'platform_net_revenue' => $row['net_fee'] + $platformSales - $row['subsidy'] - $providerFees,
                'refunds' => $row['refunds'],
                'discounts' => $row['discount_platform'] + $row['discount_center'],
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<string, array{count: int, success_count: int, amount: int, related_amount: int}>  $breakdown
     * @return list<array{key: string, purchases_count: int, gmv: int, share: float}>
     */
    private function shares($breakdown): array
    {
        $total = $breakdown->sum('amount');

        return $breakdown->map(fn (array $row, string $key): array => [
            'key' => $key,
            'purchases_count' => $row['count'],
            'gmv' => $row['amount'],
            'share' => Kpi::rate($row['amount'], $total),
        ])->values()->all();
    }

    /**
     * @return list<array{city_id: int|null, name: string|null, purchases_count: int, gmv: int, share: float}>
     */
    private function byCity(AnalyticsPeriod $period): array
    {
        $rows = $this->reader->perCity($period, $period->from, $period->to, ['purchases_count', 'gmv'])->sortByDesc('gmv');
        $cities = City::query()->whereIn('id', $rows->pluck('city_id')->filter())->get()->keyBy('id');
        $total = $rows->sum('gmv');

        return $rows->map(fn (object $row): array => [
            'city_id' => $row->city_id === null ? null : (int) $row->city_id,
            'name' => $row->city_id === null ? null : $cities->get($row->city_id)?->name,
            'purchases_count' => (int) $row->purchases_count,
            'gmv' => (int) $row->gmv,
            'share' => Kpi::rate((int) $row->gmv, $total),
        ])->values()->all();
    }
}
