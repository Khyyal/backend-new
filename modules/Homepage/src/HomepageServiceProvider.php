<?php

namespace Modules\Homepage;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Centers\Models\Center;
use Modules\Homepage\Models\Banner;
use Modules\Homepage\Models\HomeSection;
use Modules\Homepage\Models\HomeSectionItem;
use Modules\Homepage\Policies\BannerPolicy;
use Modules\Homepage\Policies\HomeSectionPolicy;
use Modules\Homepage\Services\HomeContentCache;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Models\Promotion;
use Modules\Services\Models\Service;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HomepageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'homepage');

        Gate::policy(HomeSection::class, HomeSectionPolicy::class);
        Gate::policy(Banner::class, BannerPolicy::class);

        RateLimiter::for('home-events', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('home-events:'.($request->user('client')?->getKey() ?? $request->ip())));

        $this->flushContentOnChanges();

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(function (): void {
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/client.php';
            });
    }

    /**
     * Anything a home section shows or points at: changing it retires the cached payloads.
     */
    private function flushContentOnChanges(): void
    {
        $flush = fn () => $this->app->make(HomeContentCache::class)->bump();

        foreach ([HomeSection::class, HomeSectionItem::class, Banner::class, Center::class, Service::class, Promotion::class, Campaign::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }

        foreach ([Center::class, Service::class, Promotion::class, Campaign::class] as $model) {
            $model::restored($flush);
        }

        $flushBannerImage = function (Media $media) use ($flush): void {
            if ($media->model_type === (new Banner)->getMorphClass()) {
                $flush();
            }
        };

        Media::saved($flushBannerImage);
        Media::deleted($flushBannerImage);
    }
}
