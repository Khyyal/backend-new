<?php

namespace Modules\Promotion;

use Illuminate\Support\ServiceProvider;

class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'promotion');

        require __DIR__.'/../routes/admin.php';
        require __DIR__.'/../routes/center.php';
    }
}
