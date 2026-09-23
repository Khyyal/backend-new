<?php

namespace Modules\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider  extends ServiceProvider
{
    public function boot(): void
    {
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
