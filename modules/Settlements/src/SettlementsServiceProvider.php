<?php

namespace Modules\Settlements;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Centers\Models\Center;
use Modules\Purchase\Models\Purchase;
use Modules\Settlements\Console\GenerateCenterPayoutsCommand;
use Modules\Settlements\Events\CenterPayoutPaid;
use Modules\Settlements\Listeners\NotifyCenterPayoutPaid;
use Modules\Settlements\Listeners\SyncCenterLedger;
use Modules\Settlements\Models\CenterLedgerEntry;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Policies\CenterPayoutPolicy;

/**
 * Center settlements: the ledger of what the platform and each center owe
 * each other, and the weekly payouts that settle it.
 */
class SettlementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/settlements.php',
            'settlements'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'settlements');

        $this->registerRelations();

        Event::subscribe(SyncCenterLedger::class);
        Event::listen(CenterPayoutPaid::class, NotifyCenterPayoutPaid::class);

        Gate::policy(CenterPayout::class, CenterPayoutPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCenterPayoutsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(GenerateCenterPayoutsCommand::class)->weeklyOn(0, '00:00')->withoutOverlapping();
        });

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(function (): void {
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/center.php';
            });
    }

    /**
     * Relations from other modules' models into settlements, kept here so
     * those modules do not depend on this one.
     */
    private function registerRelations(): void
    {
        Purchase::resolveRelationUsing(
            'ledgerEntries',
            fn (Purchase $purchase): HasMany => $purchase->hasMany(CenterLedgerEntry::class)
        );

        Center::resolveRelationUsing(
            'payouts',
            fn (Center $center): HasMany => $center->hasMany(CenterPayout::class)
        );
    }
}
