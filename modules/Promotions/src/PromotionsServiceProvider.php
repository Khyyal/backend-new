<?php

namespace Modules\Promotions;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Promotions\Console\ReconcilePromotionCountersCommand;
use Modules\Promotions\Contracts\PromotionRedeemer;
use Modules\Promotions\Contracts\PromotionResolver;
use Modules\Promotions\Contracts\ServicePriceDisplay;
use Modules\Promotions\Listeners\ReleaseRedemptionsOnPurchaseClosed;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Policies\CampaignPolicy;
use Modules\Promotions\Policies\CouponPolicy;
use Modules\Promotions\Policies\PromotionPolicy;
use Modules\Promotions\Services\ActivePromotionRepository;
use Modules\Promotions\Services\PromotionResolverService;
use Modules\Promotions\Services\RedemptionService;
use Modules\Promotions\Services\ServicePriceDisplayService;

class PromotionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PromotionResolver::class, PromotionResolverService::class);
        $this->app->scoped(PromotionRedeemer::class, RedemptionService::class);
        $this->app->scoped(ServicePriceDisplay::class, ServicePriceDisplayService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'promotions');

        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcilePromotionCountersCommand::class]);
        }

        Event::subscribe(ReleaseRedemptionsOnPurchaseClosed::class);

        // Only requests that actually enter a code are throttled, to slow down code enumeration.
        RateLimiter::for('coupon', function (Request $request): Limit {
            $user = $request->user();

            return $request->filled('coupon_code')
                ? Limit::perMinute(10)->by('coupon:'.($user !== null ? $user->getMorphClass().':'.$user->getKey() : $request->ip()))
                : Limit::none();
        });

        $flush = fn () => $this->app->make(ActivePromotionRepository::class)->flush();
        Promotion::saved($flush);
        Promotion::deleted($flush);
        Promotion::restored($flush);

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(function (): void {
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/center.php';
                require __DIR__.'/../routes/client.php';
            });
    }
}
