<?php

namespace Modules\Billing;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Billing\Console\ReleaseUnpaidSubscriptionsCommand;
use Modules\Billing\Listeners\AbandonCancelledSubscription;
use Modules\Billing\Listeners\ActivatePaidSubscription;
use Modules\Billing\Listeners\RefundLateSubscriptionPayment;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Policies\SubscriptionPolicy;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseConfirmed;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/billing.php',
            'billing'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'billing');

        require __DIR__.'/../routes/admin.php';
        require __DIR__.'/../routes/center.php';

        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(function (): void {
                require __DIR__.'/../routes/admin_subscriptions.php';
            });

        Relation::morphMap([
            'subscription' => Subscription::class,
        ]);

        Event::listen(PurchaseConfirmed::class, ActivatePaidSubscription::class);
        Event::listen(PurchaseCancelled::class, AbandonCancelledSubscription::class);
        Event::listen(PaymentSucceeded::class, RefundLateSubscriptionPayment::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReleaseUnpaidSubscriptionsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ReleaseUnpaidSubscriptionsCommand::class)->everyFiveMinutes()->withoutOverlapping();
        });
    }
}
