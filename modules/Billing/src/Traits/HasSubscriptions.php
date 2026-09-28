<?php

namespace Modules\Billing\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionLifecycleService;

trait HasSubscriptions
{
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    public function activeSubscriptions(): MorphMany
    {
        return $this->subscriptions()->whereIn('status', [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trialing,
        ]);
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->activeSubscriptions()
            ->latest('starts_at')
            ->first();
    }

    public function subscribe(Plan $plan, array $options = []): Subscription
    {
        return app(SubscriptionLifecycleService::class)->subscribe($this, $plan);
    }
}
