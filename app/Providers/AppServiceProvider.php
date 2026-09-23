<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            $modules = ['Modules\\Support', 'Modules\\Centers', 'Modules\\Clients'];
            foreach ($modules as $module) {
                if (str_starts_with($modelName, $module.'\\Models\\')) {
                    $basename = class_basename($modelName);

                    return $module.'\\Database\\Factories\\'.$basename.'Factory';
                }
            }

            return 'Database\\Factories\\'.$modelName.'Factory';
        });
    }
}
