<?php

namespace Modules\Reservations;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Purchase\Events\PaymentSucceeded;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Reservations\Console\Commands\ExpirePackagesCommand;
use Modules\Reservations\Console\Commands\ReleaseUnpaidBookingsCommand;
use Modules\Reservations\Listeners\ConfirmPurchasedReservations;
use Modules\Reservations\Listeners\RefundLatePayment;
use Modules\Reservations\Listeners\ReleaseCancelledPurchase;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Models\Service;

class ReservationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/reservations.php',
            'reservations'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'reservations');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        Relation::morphMap([
            'reservation' => Reservation::class,
            'client_package' => ClientPackage::class,
        ]);

        // Lets availability eager-load a service's reservations without Services depending on Reservations.
        Service::resolveRelationUsing('reservations', fn (Service $service) => $service->hasMany(Reservation::class));

        Event::listen(PurchaseConfirmed::class, ConfirmPurchasedReservations::class);
        Event::listen(PurchaseCancelled::class, ReleaseCancelledPurchase::class);
        Event::listen(PaymentSucceeded::class, RefundLatePayment::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpirePackagesCommand::class,
                ReleaseUnpaidBookingsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ReleaseUnpaidBookingsCommand::class)->everyFiveMinutes()->withoutOverlapping();
            $schedule->command(ExpirePackagesCommand::class)->hourly()->withoutOverlapping();
        });
    }
}
