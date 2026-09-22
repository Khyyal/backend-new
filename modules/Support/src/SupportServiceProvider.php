<?php

namespace Modules\Support;

use Illuminate\Support\ServiceProvider;

class SupportServiceProvider  extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    }
}
