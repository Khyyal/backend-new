<?php

namespace Modules\Analytics\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Enums\BreakdownDimension;
use Modules\Analytics\Support\MerchantScope;
use Modules\Centers\Models\Center;
use Modules\Promotions\Enums\RedemptionStatus;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Settlements\Enums\CenterLedgerEntryType;

/**
 * Rebuilds one UTC day of analytics_daily_center_stats and
 * analytics_daily_breakdowns from the transactional tables. The day's rows
 * are replaced in one transaction, so running it again changes nothing.
 *
 * Dating: purchases, payments, discounts and promotions by their
 * `created_at`; fees, subsidies and refunds by the ledger entry's
 * `created_at`; provider fees by `paid_at`; reservation outcomes by the
 * reservation's service `date`, bookings made by its `created_at`; financial
 * baseline corrections by the baseline's `cutoff_at`.
 */
class BuildDailyCenterStats
{
    private CarbonImmutable $start;

    private CarbonImmutable $end;

    /** @var array<int, array<string, int>> */
    private array $facts = [];

    /** @var list<array{center_id: int, dimension: string, dimension_key: string, count: int, success_count: int, amount: int, related_amount: int}> */
    private array $breakdowns = [];

    public function execute(CarbonImmutable $date): void
    {
        $this->start = $date->startOfDay();
        $this->end = $date->endOfDay();
        $this->facts = [];
        $this->breakdowns = [];

        $this->purchases();
        $this->discounts();
        $this->ledger();
        $this->baselines();
        $this->providerFees();
        $this->newBuyers();
        $this->payments();
        $this->bookingsMade();
        $this->reservationOutcomes();
        $this->ratings();

        $this->channelBreakdown();
        $this->serviceTypeBreakdown();
        $this->paymentBreakdowns();
        $this->promotionBreakdowns();

        $this->write($date);
    }

    private function purchases(): void
    {
        $rows = MerchantScope::merchants(DB::table('purchases'))
            ->whereBetween('created_at', [$this->start, $this->end])
            ->select(['merchant_type', 'merchant_id'])
            ->selectRaw('count(*) as all_purchases_count')
            ->selectRaw($this->countedCase('1').' as purchases_count', MerchantScope::countedStatuses())
            ->selectRaw($this->countedCase('total_amount').' as gmv', MerchantScope::countedStatuses())
            ->groupBy(['merchant_type', 'merchant_id'])
            ->get();

        $this->collect($rows, ['all_purchases_count', 'purchases_count', 'gmv']);
    }

    private function discounts(): void
    {
        $rows = MerchantScope::counted(DB::table('purchase_adjustments')->join('purchases', 'purchases.id', '=', 'purchase_adjustments.purchase_id'))
            ->whereBetween('purchases.created_at', [$this->start, $this->end])
            ->select(['purchases.merchant_type', 'purchases.merchant_id'])
            ->selectRaw('coalesce(sum(purchase_adjustments.amount), 0) as discount_total')
            ->selectRaw('coalesce(sum(purchase_adjustments.center_share_amount), 0) as discount_center')
            ->groupBy(['purchases.merchant_type', 'purchases.merchant_id'])
            ->get()
            ->map(function (object $row): object {
                $row->discount_platform = (int) $row->discount_total - (int) $row->discount_center;

                return $row;
            });

        $this->collect($rows, ['discount_platform', 'discount_center']);
    }

    /**
     * Ledger amounts are signed from the center's side; fees and refunds are
     * debits, so they are negated into the platform's view.
     */
    private function ledger(): void
    {
        $rows = DB::table('center_ledger_entries')
            ->whereBetween('created_at', [$this->start, $this->end])
            ->select(['center_id', 'type'])
            ->selectRaw('coalesce(sum(amount), 0) as total')
            ->groupBy(['center_id', 'type'])
            ->get();

        foreach ($rows as $row) {
            $type = CenterLedgerEntryType::from($row->type);
            $total = (int) $row->total;
            $column = match (true) {
                in_array($type, CenterLedgerEntryType::feeTypes(), true) => 'net_fee',
                in_array($type, CenterLedgerEntryType::subsidyTypes(), true) => 'subsidy',
                $type === CenterLedgerEntryType::Refund => 'refunds',
                default => null,
            };

            if ($column !== null) {
                $this->add((int) $row->center_id, $column, $column === 'subsidy' ? $total : -$total);
            }
        }
    }

    /**
     * A confirmed financial baseline corrects a migrated center's totals to
     * the figures finance holds. The whole correction lands on the cutoff day.
     */
    private function baselines(): void
    {
        $rows = DB::table('center_financial_baselines')
            ->whereNotNull('confirmed_at')
            ->whereBetween('cutoff_at', [$this->start, $this->end])
            ->get();

        foreach ($rows as $row) {
            foreach (['net_fee' => 'platform_fees', 'gmv' => 'gross_sales', 'refunds' => 'refunds'] as $column => $figure) {
                $target = $row->{"target_{$figure}"};

                if ($target !== null) {
                    $this->add((int) $row->center_id, $column, (int) $target - (int) $row->{"migrated_{$figure}"});
                }
            }
        }
    }

    private function providerFees(): void
    {
        $rows = MerchantScope::merchants(DB::table('payments')->join('purchases', 'purchases.id', '=', 'payments.purchase_id'))
            ->whereBetween('payments.paid_at', [$this->start, $this->end])
            ->whereNotNull('payments.provider_fee_amount')
            ->select(['purchases.merchant_type', 'purchases.merchant_id'])
            ->selectRaw('coalesce(sum(payments.provider_fee_amount), 0) as provider_fees')
            ->groupBy(['purchases.merchant_type', 'purchases.merchant_id'])
            ->get();

        $this->collect($rows, ['provider_fees']);
    }

    /**
     * Clients whose first counted purchase at the merchant falls on the day.
     */
    private function newBuyers(): void
    {
        $firsts = MerchantScope::counted(DB::table('purchases'))
            ->where('buyer_type', MerchantScope::clientMorph())
            ->where('created_at', '<=', $this->end)
            ->select(['merchant_type', 'merchant_id', 'buyer_id'])
            ->groupBy(['merchant_type', 'merchant_id', 'buyer_id'])
            ->havingRaw('min(created_at) >= ?', [$this->start]);

        $rows = DB::query()->fromSub($firsts, 'firsts')
            ->select(['merchant_type', 'merchant_id'])
            ->selectRaw('count(*) as new_buyers')
            ->groupBy(['merchant_type', 'merchant_id'])
            ->get();

        $this->collect($rows, ['new_buyers']);
    }

    /**
     * Online payment attempts made on the day; retries are separate attempts.
     */
    private function payments(): void
    {
        $succeeded = [PaymentStatus::Succeeded->value, PaymentStatus::Refunded->value];
        $failed = [PaymentStatus::Failed->value, PaymentStatus::Cancelled->value, PaymentStatus::Expired->value];

        $rows = MerchantScope::merchants(DB::table('payments')->join('purchases', 'purchases.id', '=', 'payments.purchase_id'))
            ->whereBetween('payments.created_at', [$this->start, $this->end])
            ->where('payments.method', PaymentMethod::Online->value)
            ->select(['purchases.merchant_type', 'purchases.merchant_id'])
            ->selectRaw('count(*) as payments_attempted')
            ->selectRaw($this->inCase('payments.status', $succeeded, '1').' as payments_succeeded', $succeeded)
            ->selectRaw($this->inCase('payments.status', $failed, '1').' as payments_failed', $failed)
            ->groupBy(['purchases.merchant_type', 'purchases.merchant_id'])
            ->get();

        $this->collect($rows, ['payments_attempted', 'payments_succeeded', 'payments_failed']);
    }

    private function bookingsMade(): void
    {
        $rows = DB::table('reservations')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->start, $this->end])
            ->select('center_id')
            ->selectRaw('count(*) as bookings_made')
            ->selectRaw('coalesce(sum(quantity), 0) as tickets_booked')
            ->groupBy('center_id')
            ->get();

        $this->collectByCenter($rows, ['bookings_made', 'tickets_booked']);
    }

    /**
     * Reservations whose service date is the day, by where they ended up.
     */
    private function reservationOutcomes(): void
    {
        $rows = DB::table('reservations')
            ->whereNull('deleted_at')
            ->where('date', $this->start->toDateString())
            ->select('center_id')
            ->selectRaw('count(*) as reservations_scheduled')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as reservations_completed', [ReservationStatus::Completed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as reservations_no_show', [ReservationStatus::NoShow->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as reservations_cancelled', [ReservationStatus::Cancelled->value])
            ->selectRaw(
                'sum(case when status = ? and cancelled_by_type = ? then 1 else 0 end) as cancellations_by_client',
                [ReservationStatus::Cancelled->value, MerchantScope::clientMorph()],
            )
            ->groupBy('center_id')
            ->get();

        $this->collectByCenter($rows, ['reservations_scheduled', 'reservations_completed', 'reservations_no_show', 'reservations_cancelled', 'cancellations_by_client']);
    }

    private function ratings(): void
    {
        $rows = DB::table('ratings')
            ->where('rateable_type', MerchantScope::centerMorph())
            ->whereBetween('created_at', [$this->start, $this->end])
            ->selectRaw('rateable_id as center_id')
            ->selectRaw('count(*) as ratings_count')
            ->selectRaw('coalesce(sum(stars), 0) as ratings_stars_sum')
            ->groupBy('rateable_id')
            ->get();

        $this->collectByCenter($rows, ['ratings_count', 'ratings_stars_sum']);
    }

    private function channelBreakdown(): void
    {
        $rows = MerchantScope::counted(DB::table('purchases'))
            ->whereBetween('created_at', [$this->start, $this->end])
            ->select(['merchant_type', 'merchant_id', 'channel'])
            ->selectRaw('count(*) as count')
            ->selectRaw('coalesce(sum(total_amount), 0) as amount')
            ->groupBy(['merchant_type', 'merchant_id', 'channel'])
            ->get();

        foreach ($rows as $row) {
            $this->breakdown(MerchantScope::merchantKey($row->merchant_type, $row->merchant_id), BreakdownDimension::Channel, $row->channel, (int) $row->count, amount: (int) $row->amount);
        }
    }

    /**
     * Counted purchases split by the type of service their items are for.
     */
    private function serviceTypeBreakdown(): void
    {
        $items = collect([new Reservation, new ClientPackage, new ClientMembership])
            ->map(fn (Reservation|ClientPackage|ClientMembership $purchasable): Builder => $this->purchasedServiceItems($purchasable))
            ->reduce(fn (?Builder $union, Builder $query): Builder => $union?->unionAll($query) ?? $query);

        $rows = DB::query()->fromSub($items, 'items')
            ->join('services', 'services.id', '=', 'items.service_id')
            ->select(['items.merchant_type', 'items.merchant_id', 'services.type'])
            ->selectRaw('count(distinct items.purchase_id) as count')
            ->selectRaw('coalesce(sum(items.total_amount), 0) as amount')
            ->groupBy(['items.merchant_type', 'items.merchant_id', 'services.type'])
            ->get();

        foreach ($rows as $row) {
            $this->breakdown(MerchantScope::merchantKey($row->merchant_type, $row->merchant_id), BreakdownDimension::ServiceType, $row->type, (int) $row->count, amount: (int) $row->amount);
        }
    }

    private function purchasedServiceItems(Reservation|ClientPackage|ClientMembership $purchasable): Builder
    {
        $table = $purchasable->getTable();

        return MerchantScope::counted(
            DB::table('purchase_items')
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->join($table, "{$table}.id", '=', 'purchase_items.purchasable_id')
        )
            ->where('purchase_items.purchasable_type', $purchasable->getMorphClass())
            ->whereBetween('purchases.created_at', [$this->start, $this->end])
            ->select(["{$table}.service_id", 'purchase_items.purchase_id', 'purchase_items.total_amount', 'purchases.merchant_type', 'purchases.merchant_id']);
    }

    private function paymentBreakdowns(): void
    {
        $succeeded = [PaymentStatus::Succeeded->value, PaymentStatus::Refunded->value];

        foreach (BreakdownDimension::paymentDimensions() as $dimension) {
            $column = match ($dimension) {
                BreakdownDimension::PaymentMethod => 'payments.method',
                BreakdownDimension::PaymentCompany => 'payments.payment_company',
                default => 'payments.payment_type',
            };

            $query = MerchantScope::merchants(DB::table('payments')->join('purchases', 'purchases.id', '=', 'payments.purchase_id'))
                ->whereBetween('payments.created_at', [$this->start, $this->end]);

            if ($dimension !== BreakdownDimension::PaymentMethod) {
                $query->where('payments.method', PaymentMethod::Online->value);
            }

            $rows = $query
                ->select(['purchases.merchant_type', 'purchases.merchant_id'])
                ->selectRaw("{$column} as dimension_key")
                ->selectRaw('count(*) as count')
                ->selectRaw($this->inCase('payments.status', $succeeded, '1').' as success_count', $succeeded)
                ->selectRaw($this->inCase('payments.status', $succeeded, 'payments.amount').' as amount', $succeeded)
                ->groupBy(['purchases.merchant_type', 'purchases.merchant_id', $column])
                ->get();

            foreach ($rows as $row) {
                $this->breakdown(
                    MerchantScope::merchantKey($row->merchant_type, $row->merchant_id),
                    $dimension,
                    $row->dimension_key,
                    (int) $row->count,
                    successCount: (int) $row->success_count,
                    amount: (int) $row->amount,
                );
            }
        }
    }

    /**
     * Redemptions still applied on purchases made on the day; released ones
     * (cancelled purchases) drop out when the day is restated.
     */
    private function promotionBreakdowns(): void
    {
        foreach ([[BreakdownDimension::Promotion, 'promotion_id'], [BreakdownDimension::Coupon, 'coupon_id']] as [$dimension, $column]) {
            $rows = MerchantScope::merchants(DB::table('promotion_redemptions')->join('purchases', 'purchases.id', '=', 'promotion_redemptions.purchase_id'))
                ->where('promotion_redemptions.status', RedemptionStatus::Applied->value)
                ->whereNotNull("promotion_redemptions.{$column}")
                ->whereBetween('purchases.created_at', [$this->start, $this->end])
                ->select(['purchases.merchant_type', 'purchases.merchant_id'])
                ->selectRaw("promotion_redemptions.{$column} as dimension_key")
                ->selectRaw('count(*) as count')
                ->selectRaw('coalesce(sum(promotion_redemptions.discount_amount), 0) as amount')
                ->selectRaw('coalesce(sum(purchases.total_amount), 0) as related_amount')
                ->groupBy(['purchases.merchant_type', 'purchases.merchant_id', "promotion_redemptions.{$column}"])
                ->get();

            foreach ($rows as $row) {
                $this->breakdown(
                    MerchantScope::merchantKey($row->merchant_type, $row->merchant_id),
                    $dimension,
                    $row->dimension_key,
                    (int) $row->count,
                    amount: (int) $row->amount,
                    relatedAmount: (int) $row->related_amount,
                );
            }
        }
    }

    private function write(CarbonImmutable $date): void
    {
        $day = $date->toDateString();
        $builtAt = now();
        $centerIds = array_unique([...array_keys($this->facts), ...array_column($this->breakdowns, 'center_id')]);
        $cities = Center::withTrashed()->whereIn('id', $centerIds)->pluck('city_id', 'id');

        $facts = collect($this->facts)
            ->map(fn (array $metrics, int $centerId): array => [
                'date' => $day,
                'center_id' => $centerId,
                'city_id' => $cities->get($centerId),
                ...$metrics,
                'built_at' => $builtAt,
            ])
            ->values();

        $breakdowns = collect($this->breakdowns)
            ->map(fn (array $row): array => [
                'date' => $day,
                'city_id' => $cities->get($row['center_id']),
                ...$row,
                'built_at' => $builtAt,
            ]);

        DB::transaction(function () use ($day, $facts, $breakdowns): void {
            DB::table('analytics_daily_center_stats')->where('date', $day)->delete();
            DB::table('analytics_daily_breakdowns')->where('date', $day)->delete();

            $this->insertAligned('analytics_daily_center_stats', $facts);
            $breakdowns->chunk(500)->each(fn (Collection $chunk) => DB::table('analytics_daily_breakdowns')->insert($chunk->values()->all()));
        });
    }

    /**
     * Rows only carry the metrics that had data; give every row every column.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function insertAligned(string $table, Collection $rows): void
    {
        $columns = collect($rows->all())->flatMap(fn (array $row): array => array_keys($row))->unique();
        $template = $columns->mapWithKeys(fn (string $column): array => [$column => 0])->all();

        $rows->map(fn (array $row): array => [...$template, ...$row])
            ->chunk(500)
            ->each(fn (Collection $chunk) => DB::table($table)->insert($chunk->values()->all()));
    }

    /**
     * @param  Collection<int, object>  $rows  Rows with `merchant_type` and `merchant_id`.
     * @param  list<string>  $columns
     */
    private function collect(Collection $rows, array $columns): void
    {
        foreach ($rows as $row) {
            $centerId = MerchantScope::merchantKey($row->merchant_type, $row->merchant_id);

            foreach ($columns as $column) {
                $this->add($centerId, $column, (int) $row->{$column});
            }
        }
    }

    /**
     * @param  Collection<int, object>  $rows  Rows with `center_id`.
     * @param  list<string>  $columns
     */
    private function collectByCenter(Collection $rows, array $columns): void
    {
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $this->add((int) $row->center_id, $column, (int) $row->{$column});
            }
        }
    }

    private function add(int $centerId, string $column, int $value): void
    {
        $this->facts[$centerId][$column] = ($this->facts[$centerId][$column] ?? 0) + $value;
    }

    private function breakdown(int $centerId, BreakdownDimension $dimension, int|string|null $key, int $count, int $successCount = 0, int $amount = 0, int $relatedAmount = 0): void
    {
        $this->breakdowns[] = [
            'center_id' => $centerId,
            'dimension' => $dimension->value,
            'dimension_key' => $key === null || $key === '' ? 'unknown' : (string) $key,
            'count' => $count,
            'success_count' => $successCount,
            'amount' => $amount,
            'related_amount' => $relatedAmount,
        ];
    }

    private function countedCase(string $value): string
    {
        return $this->inCase('status', MerchantScope::countedStatuses(), $value);
    }

    /**
     * `coalesce(sum(case when <column> in (?, ...) then <value> else 0 end), 0)`; bind the values in order.
     *
     * @param  list<string>  $values
     */
    private function inCase(string $column, array $values, string $value): string
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return "coalesce(sum(case when {$column} in ({$placeholders}) then {$value} else 0 end), 0)";
    }
}
