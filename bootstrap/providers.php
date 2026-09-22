<?php

use App\Providers\AppServiceProvider;
use Modules\Centers\CentersServiceProvider;
use Modules\Support\SupportServiceProvider;

return [
    AppServiceProvider::class,
    SupportServiceProvider::class,
    CentersServiceProvider::class,
];
