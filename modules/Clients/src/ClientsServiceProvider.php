<?php

namespace Modules\Clients;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Clients\Http\Middleware\SetLocaleFromAcceptLanguage;
use Modules\Clients\Http\Middleware\TrackClientSession;

class ClientsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app['router']->aliasMiddleware('set.locale.from.accept.language', SetLocaleFromAcceptLanguage::class);
        $this->app['router']->aliasMiddleware('track.client.session', TrackClientSession::class);

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

    }
}
