<?php

namespace Modules\Reservations;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Reservations\Listeners\ConfirmPurchasedReservations;
use Modules\Reservations\Listeners\ReleaseCancelledPurchase;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;

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

        Event::listen(PurchaseConfirmed::class, ConfirmPurchasedReservations::class);
        Event::listen(PurchaseCancelled::class, ReleaseCancelledPurchase::class);
    }
}
