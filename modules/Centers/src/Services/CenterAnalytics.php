<?php

namespace Modules\Centers\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Service;
use Modules\Services\Models\Trainer;

/**
 * Center-wide analytics over a date range: KPIs compared with the same-length
 * span right before it, plus the top services and trainers. Only confirmed and
 * completed purchases count.
 */
class CenterAnalytics
{
    /**
     * @return array{
     *     period: array{from: string, to: string, previous_from: string, previous_to: string},
     *     kpis: array<string, array{value: int|float, previous: int|float, growth: ?float}>,
     *     top_services: list<array{service_id: int, name: ?string, type: ?string, purchases_count: int}>,
     *     top_trainers: list<array{trainer_id: int, name: ?string, reservations_count: int}>,
     * }
     */
    public function overview(Center $center, CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = (int) $from->diffInDays($to->startOfDay()) + 1;
        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();

        $kpi = fn (callable $metric): array => $this->compare(
            $metric($center, $from, $to),
            $metric($center, $previousFrom, $previousTo),
        );

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'kpis' => [
                'purchases_count' => $kpi($this->purchasesCount(...)),
                'total_revenue' => $kpi($this->totalRevenue(...)),
                'new_clients' => $kpi($this->newClients(...)),
                'repeat_client_rate' => $kpi($this->repeatClientRate(...)),
            ],
            'top_services' => $this->topServices($center, $from, $to, $limit),
            'top_trainers' => $this->topTrainers($center, $from, $to, $limit),
        ];
    }

    private function purchasesCount(Center $center, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->purchases($center)->whereBetween('created_at', [$from, $to])->count();
    }

    private function totalRevenue(Center $center, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $this->purchases($center)->whereBetween('created_at', [$from, $to])->sum('total_amount');
    }

    /**
     * Clients whose first counted purchase at the center falls in the range.
     */
    private function newClients(Center $center, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $firstPurchases = $this->clientPurchases($center)
            ->where('created_at', '<=', $to)
            ->select('buyer_id')
            ->groupBy('buyer_id')
            ->havingRaw('min(created_at) >= ?', [$from]);

        return DB::query()->fromSub($firstPurchases->toBase(), 'first_purchases')->count();
    }

    /**
     * Percentage of the range's buyers with at least two counted purchases at
     * the center up to the range end (they bought before, or more than once).
     */
    private function repeatClientRate(Center $center, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $buyers = $this->clientPurchases($center)->whereBetween('created_at', [$from, $to])->select('buyer_id')->distinct();

        $buyersCount = DB::query()->fromSub($buyers->toBase(), 'buyers')->count();

        if ($buyersCount === 0) {
            return 0.0;
        }

        $repeatBuyers = $this->clientPurchases($center)
            ->where('created_at', '<=', $to)
            ->whereIn('buyer_id', $buyers->toBase())
            ->select('buyer_id')
            ->groupBy('buyer_id')
            ->havingRaw('count(*) >= 2');

        $repeatCount = DB::query()->fromSub($repeatBuyers->toBase(), 'repeat_buyers')->count();

        return round($repeatCount / $buyersCount * 100, 1);
    }

    /**
     * Services ranked by the number of counted purchases with an item
     * (reservation, client package or client membership) for them.
     *
     * @return list<array{service_id: int, name: ?string, type: ?string, purchases_count: int}>
     */
    private function topServices(Center $center, CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $items = collect([Reservation::class, ClientPackage::class, ClientMembership::class])
            ->map(fn (string $purchasable): QueryBuilder => $this->purchasedServices($center, $from, $to, new $purchasable))
            ->reduce(fn (?QueryBuilder $union, QueryBuilder $query): QueryBuilder => $union?->unionAll($query) ?? $query);

        $ranking = DB::query()
            ->fromSub($items, 'purchased_services')
            ->select('service_id')
            ->selectRaw('count(distinct purchase_id) as purchases_count')
            ->groupBy('service_id')
            ->orderByDesc('purchases_count')
            ->orderBy('service_id')
            ->limit($limit)
            ->get();

        $services = Service::withTrashed()->whereIn('id', $ranking->pluck('service_id'))->get()->keyBy('id');

        return $ranking->map(fn (object $row): array => [
            'service_id' => (int) $row->service_id,
            'name' => $services->get($row->service_id)?->name,
            'type' => $services->get($row->service_id)?->type?->value,
            'purchases_count' => (int) $row->purchases_count,
        ])->values()->all();
    }

    /**
     * Trainers ranked by the number of non-cancelled reservations made in the range.
     *
     * @return list<array{trainer_id: int, name: ?string, reservations_count: int}>
     */
    private function topTrainers(Center $center, CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $ranking = Reservation::query()
            ->where('center_id', $center->getKey())
            ->whereNotNull('trainer_id')
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed, ReservationStatus::Completed])
            ->whereBetween('created_at', [$from, $to])
            ->select('trainer_id')
            ->selectRaw('count(*) as reservations_count')
            ->groupBy('trainer_id')
            ->orderByDesc('reservations_count')
            ->orderBy('trainer_id')
            ->limit($limit)
            ->toBase()
            ->get();

        $trainers = Trainer::withTrashed()->whereIn('id', $ranking->pluck('trainer_id'))->get()->keyBy('id');

        return $ranking->map(fn (object $row): array => [
            'trainer_id' => (int) $row->trainer_id,
            'name' => $trainers->get($row->trainer_id)?->name,
            'reservations_count' => (int) $row->reservations_count,
        ])->values()->all();
    }

    /**
     * `service_id` / `purchase_id` pairs of the range's counted purchase items of one purchasable type.
     */
    private function purchasedServices(Center $center, CarbonImmutable $from, CarbonImmutable $to, Reservation|ClientPackage|ClientMembership $purchasable): QueryBuilder
    {
        $table = $purchasable->getTable();

        return DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join($table, "{$table}.id", '=', 'purchase_items.purchasable_id')
            ->where('purchase_items.purchasable_type', $purchasable->getMorphClass())
            ->where('purchases.merchant_type', $center->getMorphClass())
            ->where('purchases.merchant_id', $center->getKey())
            ->whereIn('purchases.status', $this->countedStatuses())
            ->whereNull('purchases.deleted_at')
            ->whereBetween('purchases.created_at', [$from, $to])
            ->select(["{$table}.service_id", 'purchase_items.purchase_id']);
    }

    /**
     * @return Builder<Purchase>
     */
    private function purchases(Center $center): Builder
    {
        return Purchase::query()
            ->where('merchant_type', $center->getMorphClass())
            ->where('merchant_id', $center->getKey())
            ->whereIn('status', $this->countedStatuses());
    }

    /**
     * @return Builder<Purchase>
     */
    private function clientPurchases(Center $center): Builder
    {
        return $this->purchases($center)->where('buyer_type', (new Client)->getMorphClass());
    }

    /**
     * @return array<int, string>
     */
    private function countedStatuses(): array
    {
        return [PurchaseStatus::Confirmed->value, PurchaseStatus::Completed->value];
    }

    /**
     * @return array{value: int|float, previous: int|float, growth: ?float}
     */
    private function compare(int|float $value, int|float $previous): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            'growth' => $previous == 0 ? null : round(($value - $previous) / $previous * 100, 1),
        ];
    }
}
