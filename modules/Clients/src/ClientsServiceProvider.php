<?php

namespace Modules\Clients;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Clients\Events\ClientRegistered;
use Modules\Clients\Http\Middleware\SetLocaleFromAcceptLanguage;
use Modules\Clients\Http\Middleware\TrackClientSession;
use Modules\Clients\Listeners\SendClientRegisteredNotification;
use Modules\Clients\Models\Client;
use Modules\Clients\Policies\ClientPolicy;

class ClientsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'clients');

        Gate::policy(Client::class, ClientPolicy::class);
        Event::listen(ClientRegistered::class, SendClientRegisteredNotification::class);

        $this->app['router']->aliasMiddleware('set.locale.from.accept.language', SetLocaleFromAcceptLanguage::class);
        $this->app['router']->aliasMiddleware('track.client.session', TrackClientSession::class);

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

    }
}
