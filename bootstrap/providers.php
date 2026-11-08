<?php

use App\Providers\AppServiceProvider;
use Modules\Analytics\AnalyticsServiceProvider;
use Modules\Billing\BillingServiceProvider;
use Modules\Centers\CentersServiceProvider;
use Modules\Clients\ClientsServiceProvider;
use Modules\Homepage\HomepageServiceProvider;
use Modules\Notifications\NotificationsServiceProvider;
use Modules\Promotions\PromotionsServiceProvider;
use Modules\Purchase\PurchaseServiceProvider;
use Modules\Reservations\ReservationsServiceProvider;
use Modules\Services\ServicesServiceProvider;
use Modules\Settlements\SettlementsServiceProvider;
use Modules\Support\SupportServiceProvider;

return [
    AppServiceProvider::class,
    SupportServiceProvider::class,
    CentersServiceProvider::class,
    ClientsServiceProvider::class,
    PurchaseServiceProvider::class,
    SettlementsServiceProvider::class,
    PromotionsServiceProvider::class,
    BillingServiceProvider::class,
    ServicesServiceProvider::class,
    ReservationsServiceProvider::class,
    NotificationsServiceProvider::class,
    HomepageServiceProvider::class,
    AnalyticsServiceProvider::class,
];
