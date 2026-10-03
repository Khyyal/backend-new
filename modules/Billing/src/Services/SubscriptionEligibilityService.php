<?php

namespace Modules\Billing\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;

class SubscriptionEligibilityService
{
    public function canSubscribe(Model $subscribable, Plan $plan): bool
    {
        if ($plan->status !== PlanStatus::Active) {
            return false;
        }

        $hasActive = $subscribable->subscriptions()
            ->where('plan_id', $plan->id)
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
            ])
            ->exists();

        return ! $hasActive;
    }

    public function canCancel(Subscription $subscription, ?Model $subscribable = null): bool
    {
        if ($subscribable !== null
            && ($subscription->subscribable_type !== $subscribable->getMorphClass()
                || (int) $subscription->subscribable_id !== (int) $subscribable->getKey())) {
            return false;
        }

        return in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trialing,
        ], true);
    }

    public function subscriptionCurrentlyValid(Subscription $subscription): bool
    {
        $statusValid = in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trialing,
        ], true);

        if (! $statusValid) {
            return false;
        }

        if ($subscription->ends_at === null) {
            return true;
        }

        return $subscription->ends_at->isFuture() || $subscription->ends_at->isCurrentSecond();
    }
}
