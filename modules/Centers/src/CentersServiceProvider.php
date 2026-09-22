<?php


namespace Modules\Centers;

use Illuminate\Support\ServiceProvider;
class CentersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    }
}
