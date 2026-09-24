<?php

namespace Modules\Centers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CentersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
