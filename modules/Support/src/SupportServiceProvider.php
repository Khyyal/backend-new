<?php

namespace Modules\Support;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Support\Jobs\CleanupExpiredMedia;
use Modules\Support\Listeners\DeleteExpiredNotificationTokens;
use Modules\Support\Models\SystemUser;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;
use Modules\Support\Notifications\NotificationService;
use Modules\Support\Policies\MediaPolicy;
use Modules\Support\Policies\SystemUserPolicy;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;
use Modules\Support\WhatsApp\VoxaWhatsAppGateway;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media.php', 'support-media');
        $this->mergeConfigFrom(__DIR__.'/../config/support.php', 'support');

        $this->app->bind(NotificationServiceContract::class, NotificationService::class);
        $this->app->bind(WhatsAppGateway::class, fn ($app): VoxaWhatsAppGateway => new VoxaWhatsAppGateway(
            $app->make(HttpFactory::class),
            $app->make('cache.store'),
            (array) config('services.voxa'),
        ));
    }

    public function boot(): void
    {
        Gate::policy(config('media-library.media_model'), MediaPolicy::class);
        Gate::policy(SystemUser::class, SystemUserPolicy::class);

        Event::listen(NotificationFailed::class, DeleteExpiredNotificationTokens::class);

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
