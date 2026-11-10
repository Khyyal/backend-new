<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Centers\Models\Center;

#[Signature('migrator:subscriptions {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy center subscriptions onto the migrated plans (pending checkouts are skipped)')]
class MigrateSubscriptionsCommand extends LegacyMigrator
{
    public const ENTITY = 'subscription';

    /** @var array<string, SubscriptionStatus> */
    private const STATUSES = [
        'active' => SubscriptionStatus::Active,
        'cancelled' => SubscriptionStatus::Canceled,
        'canceled' => SubscriptionStatus::Canceled,
        'expired' => SubscriptionStatus::Expired,
    ];

    protected function migrate(): void
    {
        $centerIds = DB::table('centers')->pluck('id')->flip();
        $trialDays = DB::table('plans')->pluck('trial_days', 'id');
        $centerType = (new Center)->getMorphClass();

        $this->chunkLegacy('subscriptions', function (Collection $subscriptions) use ($centerIds, $trialDays, $centerType): void {
            foreach ($subscriptions as $subscription) {
                if ($this->mappedId(self::ENTITY, $subscription->id) !== null) {
                    $this->tally('subscriptions already present');

                    continue;
                }

                $legacyStatus = strtolower(trim((string) $subscription->status));

                if ($legacyStatus === 'pending') {
                    $this->tally('subscriptions skipped (pending checkout)');

                    continue;
                }

                $status = self::STATUSES[$legacyStatus] ?? null;

                if ($status === null) {
                    $this->addWarning("Subscription #{$subscription->id}: unknown status '{$subscription->status}'; skipped.");

                    continue;
                }

                if (! $centerIds->has($subscription->barn_id)) {
                    $this->addWarning("Subscription #{$subscription->id}: barn #{$subscription->barn_id} was not migrated; skipped.");

                    continue;
                }

                $planId = $this->mappedId(MigratePlansCommand::ENTITY, $subscription->account_plan_id);

                if ($planId === null) {
                    $this->addWarning("Subscription #{$subscription->id}: plan #{$subscription->account_plan_id} was not migrated; skipped.");

                    continue;
                }

                $startsAt = Carbon::parse($subscription->starts_at)->startOfDay();
                $endsAt = $subscription->ends_at !== null
                    ? Carbon::parse($subscription->ends_at)->startOfDay()
                    : $startsAt->copy()->addMonthsNoOverflow($subscription->billing_cycle === 'yearly' ? 12 : 1);
                $isTrial = $status === SubscriptionStatus::Active && (int) ($trialDays[$planId] ?? 0) > 0;
                $finalStatus = $isTrial ? SubscriptionStatus::Trialing : $status;

                $newId = DB::table('subscriptions')->insertGetId([
                    'subscribable_type' => $centerType,
                    'subscribable_id' => $subscription->barn_id,
                    'plan_id' => $planId,
                    'status' => $finalStatus->value,
                    'trial_ends_at' => $isTrial ? $endsAt : null,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'canceled_at' => $subscription->cancelled_at,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at,
                ]);

                $this->mapId(self::ENTITY, $subscription->id, (int) $newId);
                $this->tally("subscriptions ({$finalStatus->value})");
            }
        });
    }
}
