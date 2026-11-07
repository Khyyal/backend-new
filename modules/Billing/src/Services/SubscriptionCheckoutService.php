<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Data\SubscriptionCheckoutResult;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Exceptions\SubscriptionIneligibleException;
use Modules\Billing\Models\Plan;
use Modules\Centers\Models\Center;
use Modules\Purchase\Actions\CreatePayment;
use Modules\Purchase\Actions\CreatePurchase;
use Modules\Purchase\Actions\StartOnlinePayment;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Merchant\Platform;

/**
 * Subscribes a center to a plan. A paid plan opens a pending subscription with
 * a platform purchase and an online payment initialized with its gateway (the
 * default one when no company is given); the subscription activates once the
 * purchase is confirmed. Free plans and plans with a trial start right away.
 */
class SubscriptionCheckoutService
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
        private readonly CreatePurchase $createPurchase,
        private readonly CreatePayment $createPayment,
        private readonly PaymentGatewayManager $gateways,
        private readonly StartOnlinePayment $startOnlinePayment,
    ) {}

    /**
     * @throws SubscriptionIneligibleException
     */
    public function execute(Center $center, Plan $plan, ?string $paymentCompany = null): SubscriptionCheckoutResult
    {
        if ($plan->price <= 0 || $plan->trial_days > 0) {
            return new SubscriptionCheckoutResult($this->lifecycle->subscribe($center, $plan));
        }

        return DB::transaction(function () use ($center, $plan, $paymentCompany): SubscriptionCheckoutResult {
            $subscription = $this->lifecycle->subscribe($center, $plan, SubscriptionStatus::Pending);

            $purchase = $this->createPurchase->execute(
                $center,
                new Platform,
                PurchaseSource::Center,
                [[
                    'purchasable' => $subscription,
                    'name' => $this->planName($plan),
                    'unit_price' => $plan->price,
                ]],
                channel: PurchaseChannel::CenterDashboard,
            );

            $payment = $this->createPayment->execute(
                $purchase,
                PaymentMethod::Online,
                $purchase->total_amount,
                $paymentCompany ?? $this->gateways->getDefaultDriver(),
            );

            $gateway = $this->startOnlinePayment->execute($purchase, $payment);

            return new SubscriptionCheckoutResult($subscription, $purchase, $payment, $gateway);
        });
    }

    private function planName(Plan $plan): string
    {
        $name = (array) $plan->name;

        return (string) ($name[app()->getLocale()] ?? $name['en'] ?? $plan->slug);
    }
}
