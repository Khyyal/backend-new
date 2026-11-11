<?php

namespace Modules\Search;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Search\Providers\CenterSearchProvider;
use Modules\Search\Providers\ClientMembershipSearchProvider;
use Modules\Search\Providers\ClientPackageSearchProvider;
use Modules\Search\Providers\ClientSearchProvider;
use Modules\Search\Providers\PurchaseSearchProvider;
use Modules\Search\Providers\ReservationSearchProvider;
use Modules\Search\Providers\ServiceSearchProvider;

/**
 * Admin global search: one endpoint that looks up centers, clients, reservations,
 * purchases, client packages, client memberships and services at once. Each
 * group is a GlobalSearchProvider; the order here is the order of the response.
 */
class SearchServiceProvider extends ServiceProvider
{
    public const PROVIDERS_TAG = 'search.providers';

    public function register(): void
    {
        $this->app->tag([
            CenterSearchProvider::class,
            ClientSearchProvider::class,
            ReservationSearchProvider::class,
            PurchaseSearchProvider::class,
            ClientPackageSearchProvider::class,
            ClientMembershipSearchProvider::class,
            ServiceSearchProvider::class,
        ], self::PROVIDERS_TAG);
    }

    public function boot(): void
    {
        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/admin.php');
    }
}
