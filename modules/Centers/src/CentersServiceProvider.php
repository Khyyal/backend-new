<?php

namespace Modules\Centers;

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Centers\Http\Middleware\EnsureCenterAccessScope;
use Modules\Centers\Models\Center;
use Modules\Centers\Policies\CenterPolicy;

class CentersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'centers');

        $this->app->make(Registrar::class)->aliasMiddleware(
            'center.scope',
            EnsureCenterAccessScope::class,
        );

        Gate::policy(Center::class, CenterPolicy::class);

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
