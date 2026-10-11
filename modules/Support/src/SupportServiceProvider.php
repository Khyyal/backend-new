<?php

namespace Modules\Support;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Support\Jobs\CleanupExpiredMedia;
use Modules\Support\Models\SystemUser;
use Modules\Support\Policies\MediaPolicy;
use Modules\Support\Policies\SystemUserPolicy;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media.php', 'support-media');
    }

    public function boot(): void
    {
        Gate::policy(config('media-library.media_model'), MediaPolicy::class);
        Gate::policy(SystemUser::class, SystemUserPolicy::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new CleanupExpiredMedia)->hourly();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'support');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/support'),
        ], 'support-lang');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

    }
}
