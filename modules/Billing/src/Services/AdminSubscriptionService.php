<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Data\SubscriptionCheckoutResult;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Models\Plan;
use Modules\Centers\Models\Center;

/**
 * Changes a center's subscription on behalf of an admin.
 *
 * `free` replaces the current subscription with the new plan right away and
 * without payment. `required` opens a pending subscription with a platform
 * purchase; the current one is replaced once the center pays.
 */
class AdminSubscriptionService
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
        private readonly SubscriptionCheckoutService $checkout,
    ) {}

    /**
     * @throws SubscriptionIneligibleException
     */
    public function change(Center $center, Plan $plan, bool $requirePayment, ?string $paymentCompany = null): SubscriptionCheckoutResult
    {
        if ($plan->status !== PlanStatus::Active) {
            throw new SubscriptionIneligibleException(__('billing::validation.plan_not_active'));
        }

        return $requirePayment
            ? $this->changeWithPayment($center, $plan, $paymentCompany)
            : $this->changeForFree($center, $plan);
    }

    private function changeForFree(Center $center, Plan $plan): SubscriptionCheckoutResult
    {
        return DB::transaction(function () use ($center, $plan): SubscriptionCheckoutResult {
            $this->cancelCurrent($center);

            return new SubscriptionCheckoutResult(
                $this->lifecycle->subscribe($center, $plan, SubscriptionStatus::Active, bypassEligibility: true)
            );
        });
    }

    private function changeWithPayment(Center $center, Plan $plan, ?string $paymentCompany): SubscriptionCheckoutResult
    {
        if ($plan->price <= 0 || $plan->trial_days > 0) {
            throw new SubscriptionIneligibleException(__('billing::validation.payment_not_required'));
        }

        if ($center->currentSubscription()?->plan_id === $plan->id) {
            throw new SubscriptionIneligibleException(__('billing::validation.subscription_ineligible'));
        }

        return $this->checkout->execute($center, $plan, $paymentCompany);
    }

    private function cancelCurrent(Center $center): void
    {
        $center->activeSubscriptions()
            ->lockForUpdate()
            ->get()
            ->each(fn ($subscription) => $this->lifecycle->cancel($subscription, true));
    }
}
