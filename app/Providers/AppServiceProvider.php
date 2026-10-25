<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
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
        $this->raiseMemoryLimitForApiDocs();

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

    /**
     * Generating the OpenAPI document peaks at roughly 330M, above PHP's default 128M limit.
     */
    private function raiseMemoryLimitForApiDocs(): void
    {
        $raise = static fn () => ini_set('memory_limit', '512M');

        if ($this->app->runningInConsole()) {
            Event::listen(CommandStarting::class, static function (CommandStarting $event) use ($raise): void {
                if (str_starts_with((string) $event->command, 'scramble:')) {
                    $raise();
                }
            });

            return;
        }

        if ($this->app['request']->is('docs/*')) {
            $raise();
        }
    }
}
