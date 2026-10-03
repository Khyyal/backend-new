<?php

namespace Modules\Purchase;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Merchant\Platform;

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

        Relation::morphMap([
            'platform' => Platform::class,
            'center' => Center::class,
            'client' => Client::class,
        ]);
    }
}
