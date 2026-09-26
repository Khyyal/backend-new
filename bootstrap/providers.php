<?php

use App\Providers\AppServiceProvider;
use Modules\Centers\CentersServiceProvider;
use Modules\Clients\ClientsServiceProvider;
use Modules\Purchase\PurchaseServiceProvider;
use Modules\Support\SupportServiceProvider;

return [
    AppServiceProvider::class,
    SupportServiceProvider::class,
    CentersServiceProvider::class,
    ClientsServiceProvider::class,
    PurchaseServiceProvider::class,
];
