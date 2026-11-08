<?php

namespace Modules\Analytics\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Support\MerchantScope;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Centers\Enums\CenterState;
use Modules\Settlements\Enums\CenterPayoutStatus;

/**
 * Rebuilds one UTC day of analytics_daily_platform_stats: client and center
 * distinct counts over trailing windows and the subscription position at the
 * end of the day. Center-state and payout snapshots only describe "now", so
 * they are written when building yesterday and kept as they were otherwise.
 */
class BuildDailyPlatformStats
{
    public function execute(CarbonImmutable $date): void
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();
        $isLatest = $date->isSameDay(CarbonImmutable::yesterday());

        $existing = DB::table('analytics_daily_platform_stats')->where('date', $date->toDateString())->first();

        $row = [
            'date' => $date->toDateString(),
            'signups' => DB::table('clients')->whereBetween('created_at', [$start, $end])->count(),
            'new_buyers' => $this->newBuyers($start, $end),
            'active_buyers_1d' => $this->activeBuyers($end, 1),
            'active_buyers_7d' => $this->activeBuyers($end, 7),
            'active_buyers_30d' => $this->activeBuyers($end, 30),
            'centers_total' => $this->centersAt($end),
            'new_centers' => DB::table('centers')->whereBetween('created_at', [$start, $end])->count(),
            'active_centers_30d' => $this->activeCenters($end),
            ...$this->subscriptions($start, $end),
            ...($isLatest ? $this->snapshots() : $this->keptSnapshots($existing)),
            'built_at' => now(),
        ];

        DB::table('analytics_daily_platform_stats')->upsert([$row], ['date']);
    }

    private function clientPurchases(): Builder
    {
        return MerchantScope::counted(DB::table('purchases'))->where('buyer_type', MerchantScope::clientMorph());
    }

    /**
     * Clients whose first counted purchase anywhere on the platform falls on the day.
     */
    private function newBuyers(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $firsts = $this->clientPurchases()
            ->where('created_at', '<=', $end)
            ->select('buyer_id')
            ->groupBy('buyer_id')
            ->havingRaw('min(created_at) >= ?', [$start]);

        return DB::query()->fromSub($firsts, 'firsts')->count();
    }

    private function activeBuyers(CarbonImmutable $end, int $days): int
    {
        return $this->clientPurchases()
            ->whereBetween('created_at', [$end->subDays($days - 1)->startOfDay(), $end])
            ->distinct()
            ->count('buyer_id');
    }

    private function activeCenters(CarbonImmutable $end): int
    {
        return MerchantScope::counted(DB::table('purchases'))
            ->where('merchant_type', MerchantScope::centerMorph())
            ->whereBetween('created_at', [$end->subDays(29)->startOfDay(), $end])
            ->distinct()
            ->count('merchant_id');
    }

    private function centersAt(CarbonImmutable $end): int
    {
        return DB::table('centers')
            ->where('created_at', '<=', $end)
            ->where(fn (Builder $query) => $query->whereNull('deleted_at')->orWhere('deleted_at', '>', $end))
            ->count();
    }

    /**
     * Subscriptions running at the end of the day (started, not ended and
     * not cancelled by then), split into trialing and paying, and the
     * monthly recurring revenue of the paying ones (yearly price / 12).
     *
     * @return array{subscriptions_active: int, subscriptions_trialing: int, subscriptions_new: int, subscriptions_cancelled: int, mrr: int}
     */
    private function subscriptions(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $running = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', '!=', SubscriptionStatus::Pending->value)
            ->where('subscriptions.starts_at', '<=', $end)
            ->where(fn (Builder $query) => $query->whereNull('subscriptions.ends_at')->orWhere('subscriptions.ends_at', '>', $end))
            ->where(fn (Builder $query) => $query->whereNull('subscriptions.canceled_at')->orWhere('subscriptions.canceled_at', '>', $end))
            ->get(['subscriptions.trial_ends_at', 'plans.price', 'plans.billing_interval']);

        $isTrialing = fn (object $subscription): bool => $subscription->trial_ends_at !== null
            && CarbonImmutable::parse($subscription->trial_ends_at)->greaterThan($end);

        [$trialing, $paying] = $running->partition($isTrialing);

        return [
            'subscriptions_active' => $paying->count(),
            'subscriptions_trialing' => $trialing->count(),
            'subscriptions_new' => DB::table('subscriptions')
                ->where('status', '!=', SubscriptionStatus::Pending->value)
                ->whereBetween('starts_at', [$start, $end])
                ->count(),
            'subscriptions_cancelled' => DB::table('subscriptions')->whereBetween('canceled_at', [$start, $end])->count(),
            'mrr' => (int) $paying->sum(fn (object $subscription): int => $subscription->billing_interval === BillingInterval::Yearly->value
                ? intdiv((int) $subscription->price, 12)
                : (int) $subscription->price),
        ];
    }

    /**
     * @return array{centers_active: int, centers_pending: int, centers_blocked: int, payout_unpaid_balance: int, payout_on_hold: int, payout_pending: int}
     */
    private function snapshots(): array
    {
        $states = DB::table('centers')->whereNull('deleted_at')
            ->select('state')->selectRaw('count(*) as total')->groupBy('state')
            ->pluck('total', 'state');

        $unpaid = DB::table('center_ledger_entries')
            ->whereNull('payout_id')
            ->selectRaw('coalesce(sum(amount), 0) as balance')
            ->selectRaw('coalesce(sum(case when available_at > ? then amount else 0 end), 0) as on_hold', [now()])
            ->first();

        return [
            'centers_active' => (int) $states->get(CenterState::Active->value, 0),
            'centers_pending' => (int) $states->get(CenterState::Pending->value, 0),
            'centers_blocked' => (int) $states->get(CenterState::Blocked->value, 0),
            'payout_unpaid_balance' => (int) $unpaid->balance,
            'payout_on_hold' => (int) $unpaid->on_hold,
            'payout_pending' => (int) DB::table('center_payouts')->where('status', CenterPayoutStatus::Pending->value)->sum('amount'),
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function keptSnapshots(?object $existing): array
    {
        return collect(['centers_active', 'centers_pending', 'centers_blocked', 'payout_unpaid_balance', 'payout_on_hold', 'payout_pending'])
            ->mapWithKeys(fn (string $column): array => [$column => $existing?->{$column}])
            ->all();
    }
}
