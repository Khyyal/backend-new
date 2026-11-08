<?php

namespace Modules\Exports;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Exports\Console\PruneExpiredExportsCommand;
use Modules\Exports\Models\Export;
use Modules\Exports\Policies\ExportPolicy;

/**
 * Background spreadsheet exports: tracks each requested export, notifies the
 * requester when it is ready and serves the file until it expires. The export
 * classes and the endpoints that request them live in the modules owning the
 * exported data.
 */
class ExportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/exports.php', 'exports');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'exports');

        Gate::policy(Export::class, ExportPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneExpiredExportsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PruneExpiredExportsCommand::class)->dailyAt('03:00')->withoutOverlapping();
        });

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(function (): void {
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/center.php';
            });
    }
}
