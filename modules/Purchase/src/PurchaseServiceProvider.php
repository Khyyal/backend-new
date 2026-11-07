<?php

namespace Modules\Purchase;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Listeners\ConfirmPaidPurchase;
use Modules\Purchase\Listeners\RecordPaymentProviderFee;
use Modules\Purchase\Listeners\ReversePurchaseFee;
use Modules\Purchase\Listeners\SendPurchaseNotifications;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\FeeRule;
use Modules\Purchase\Models\Merchant\Platform;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Policies\FeeRulePolicy;
use Modules\Purchase\Policies\PurchasePolicy;

class PurchaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/purchase.php',
            'purchase'
        );

        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            return new PaymentGatewayManager($app);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'purchase');

        Relation::morphMap([
            'platform' => Platform::class,
            'center' => Center::class,
            'client' => Client::class,
        ]);

        Event::listen(PaymentSucceeded::class, ConfirmPaidPurchase::class);
        Event::subscribe(SendPurchaseNotifications::class);
        Event::subscribe(ReversePurchaseFee::class);
        Event::listen(PaymentSucceeded::class, RecordPaymentProviderFee::class);

        Gate::policy(FeeRule::class, FeeRulePolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(function (): void {
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/webhooks.php';
            });
    }
}
