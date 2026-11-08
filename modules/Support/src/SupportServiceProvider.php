<?php

namespace Modules\Support;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Support\Http\Middleware\EnsureIdempotentRequest;
use Modules\Support\Jobs\CleanupExpiredMedia;
use Modules\Support\Listeners\DeleteExpiredNotificationTokens;
use Modules\Support\Models\City;
use Modules\Support\Models\IdempotencyKey;
use Modules\Support\Models\SystemRole;
use Modules\Support\Models\SystemUser;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;
use Modules\Support\Notifications\NotificationService;
use Modules\Support\Policies\ActivityLogPolicy;
use Modules\Support\Policies\CityPolicy;
use Modules\Support\Policies\MediaPolicy;
use Modules\Support\Policies\SystemRolePolicy;
use Modules\Support\Policies\SystemUserPolicy;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;
use Modules\Support\WhatsApp\VoxaWhatsAppGateway;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Support\CauserResolver;

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
        Gate::policy(SystemRole::class, SystemRolePolicy::class);
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(Activity::class, ActivityLogPolicy::class);

        $this->app->make(CauserResolver::class)->resolveUsing(function (mixed $subject = null): ?Model {
            if ($subject instanceof Model) {
                return $subject;
            }

            foreach (['system_user', 'center_user', 'client'] as $guard) {
                $user = Auth::guard($guard)->user();

                if ($user instanceof Model) {
                    return $user;
                }
            }

            return null;
        });

        Event::listen(NotificationFailed::class, DeleteExpiredNotificationTokens::class);

        $this->app['router']->aliasMiddleware('idempotent', EnsureIdempotentRequest::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new CleanupExpiredMedia)->hourly();
            $schedule->command(PruneCommand::class, ['--model' => [IdempotencyKey::class]])->hourly();
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
