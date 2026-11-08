<?php

namespace Modules\Analytics;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Analytics\Console\BuildAnalyticsCommand;
use Modules\Analytics\Models\DailyPlatformStat;
use Modules\Analytics\Policies\AnalyticsPolicy;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildAnalyticsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(BuildAnalyticsCommand::class)->dailyAt('00:30')->withoutOverlapping()->onOneServer();
        });

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/admin.php');
    }
}
