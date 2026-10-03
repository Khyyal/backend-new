<?php

namespace Modules\Billing;

use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'billing');

        require __DIR__.'/../routes/admin.php';
        require __DIR__.'/../routes/center.php';
    }
}
