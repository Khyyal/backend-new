<?php

namespace Modules\Notifications;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Console\DispatchDueCampaignsCommand;
use Modules\Notifications\Jobs\DispatchCampaignJob;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Models\WhatsAppTemplate;
use Modules\Notifications\Policies\NotificationCampaignPolicy;
use Modules\Notifications\Policies\WhatsAppTemplatePolicy;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'notifications');

        Gate::policy(NotificationCampaign::class, NotificationCampaignPolicy::class);
        Gate::policy(WhatsAppTemplate::class, WhatsAppTemplatePolicy::class);

        // Paces WhatsApp chunk jobs: messages per minute divided by the chunk size.
        RateLimiter::for('voxa', fn (): Limit => Limit::perMinute(
            max(1, intdiv((int) config('services.voxa.per_minute', 600), DispatchCampaignJob::WHATSAPP_CHUNK_SIZE)),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([DispatchDueCampaignsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('notifications:dispatch-due-campaigns')->everyMinute()->withoutOverlapping()->onOneServer();
        });

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');
    }
}
