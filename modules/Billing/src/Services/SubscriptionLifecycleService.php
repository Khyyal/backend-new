<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Events\SubscriptionCanceled;
use Modules\Billing\Events\SubscriptionCreated;
use Modules\Billing\Events\SubscriptionRenewed;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly SubscriptionEligibilityService $eligibility,
        private readonly BillingIntervalCalculator $calculator,
    ) {}

    public function subscribe(
        Model $subscribable,
        Plan $plan,
        ?SubscriptionStatus $overrideStatus = null,
        bool $bypassEligibility = false,
    ): Subscription {
        return DB::transaction(function () use ($subscribable, $plan, $overrideStatus, $bypassEligibility) {
            $lockedPlan = Plan::query()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $bypassEligibility && ! $this->eligibility->canSubscribe($subscribable, $lockedPlan)) {
                throw new SubscriptionIneligibleException(
                    __('billing::validation.subscription_ineligible')
                );
            }

            $now = Carbon::now();
            $startsAt = $now;
            $trialEndsAt = $this->calculator->trialEndsAt($lockedPlan, $startsAt);

            $hasTrial = $trialEndsAt !== null;
            $status = $overrideStatus ?? ($hasTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::Active);

            if ($status === SubscriptionStatus::Trialing && ! $hasTrial) {
                $status = SubscriptionStatus::Active;
            }

            $endsAt = $this->calculator->nextBillingDate($startsAt, $lockedPlan->billing_interval, 1);

            $subscription = Subscription::query()->create([
                'subscribable_type' => $subscribable->getMorphClass(),
                'subscribable_id' => $subscribable->getKey(),
                'plan_id' => $lockedPlan->id,
                'status' => $status,
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'canceled_at' => null,
            ]);

            DB::afterCommit(function () use ($subscription) {
                event(new SubscriptionCreated($subscription));
            });

            return $subscription;
        });
    }

    public function cancel(Subscription $subscription, bool $cancelImmediately = false): Subscription
    {
        return DB::transaction(function () use ($subscription, $cancelImmediately) {
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            $subscribable = $locked->subscribable;

            if ($subscribable instanceof Model && ! $this->eligibility->canCancel($locked, $subscribable)) {
                throw new SubscriptionIneligibleException(
                    __('billing::validation.subscription_ineligible')
                );
            }

            $now = Carbon::now();

            $locked->status = SubscriptionStatus::Canceled;
            $locked->canceled_at = $now;

            if ($cancelImmediately) {
                $locked->ends_at = $now;
            }

            $locked->save();

            DB::afterCommit(function () use ($locked) {
                event(new SubscriptionCanceled($locked));
            });

            return $locked;
        });
    }

    public function renew(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->with('plan')
                ->lockForUpdate()
                ->firstOrFail();

            $plan = $locked->plan;

            Plan::query()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->exists();

            $startsAt = $locked->ends_at !== null
                ? Carbon::instance($locked->ends_at)
                : Carbon::now();

            $endsAt = $this->calculator->nextBillingDate($startsAt, $plan->billing_interval, 1);

            $new = Subscription::query()->create([
                'subscribable_type' => $locked->subscribable_type,
                'subscribable_id' => $locked->subscribable_id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'canceled_at' => null,
            ]);

            DB::afterCommit(function () use ($new) {
                event(new SubscriptionRenewed($new));
            });

            return $new;
        });
    }

    public function expire(Subscription $subscription): Subscription
    {
        $subscription->status = SubscriptionStatus::Expired;
        $subscription->save();

        return $subscription;
    }
}
