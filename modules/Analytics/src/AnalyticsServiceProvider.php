<?php

namespace Modules\Analytics;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Analytics\Console\BuildAnalyticsCommand;
use Modules\Analytics\Listeners\RebuildBaselineCutoffDay;
use Modules\Analytics\Models\DailyPlatformStat;
use Modules\Analytics\Policies\AnalyticsPolicy;
use Modules\Settlements\Events\CenterFinancialBaselineConfirmed;

/**
 * Platform analytics: nightly rollups of the transactional modules and the
 * admin dashboards that read only from them.
 */
class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/analytics.php', 'analytics');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'analytics');

        Gate::policy(DailyPlatformStat::class, AnalyticsPolicy::class);

        Event::listen(CenterFinancialBaselineConfirmed::class, RebuildBaselineCutoffDay::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildAnalyticsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(BuildAnalyticsCommand::class)->dailyAt('00:30')->withoutOverlapping()->onOneServer();
        });

        RateLimiter::for('engagement-events', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('engagement-events:'.($request->user('client')?->getKey() ?? $request->ip())));

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(function (): void {
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/client.php';
            });
    }
}
