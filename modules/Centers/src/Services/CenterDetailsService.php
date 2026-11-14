<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Centers\Models\Center;
use Modules\Promotions\Contracts\ServicePriceDisplay;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Support\Haversine;

class CenterDetailsService
{
    /**
     * Number of highest ratings loaded with `include=ratings`.
     */
    public const int RATINGS_LIMIT = 10;

    public function __construct(
        private readonly ServicePriceDisplay $priceDisplay,
    ) {}

    /**
     * Find a visible center by slug with its active services overview.
     * Resort `start_price` is the minimum day price (add-ons excluded).
     * `distance_km` is set only when both coordinates are given.
     * With `$withRatings` it also loads the rating stats and the highest ratings.
     *
     * @throws ModelNotFoundException
     */
    public function findBySlug(string $slug, ?float $lat = null, ?float $lng = null, bool $withRatings = false): Center
    {
        $query = Center::query()
            ->visible()
            ->where('centers.slug', $slug)
            ->select('centers.*')
            ->with([
                'city',
                'tags',
                'media',
                'services' => fn ($services) => $services
                    ->where('status', ActivationStatus::ACTIVE->value)
                    ->withStartPrice()
                    ->with('media')
                    ->orderBy('id'),
            ]);

        if ($lat !== null && $lng !== null) {
            $query->selectRaw(
                Haversine::sql('centers.lat', 'centers.lng').' as distance_km',
                Haversine::bindings($lat, $lng),
            );
        }

        if ($withRatings) {
            $this->withTopRatings($query);
        }

        $center = $query->firstOrFail();

        $center->setAttribute('service_display_prices', $this->priceDisplay->forServices($center->services));

        return $center;
    }

    /**
     * Find the visible center whose custom site is enabled on the given domain.
     * With `$withRatings` it also loads the rating stats and the highest ratings.
     *
     * @throws ModelNotFoundException
     */
    public function findByDomain(string $domain, bool $withRatings = false): Center
    {
        return Center::query()
            ->visible()
            ->where('custom_site_enabled', true)
            ->where('domain', strtolower(trim($domain)))
            ->select(['centers.id', 'centers.slug', 'centers.site_theme'])
            ->when($withRatings, fn (Builder $query) => $this->withTopRatings($query))
            ->firstOrFail();
    }

    /**
     * Add rating stats and eager-load the RATINGS_LIMIT highest ratings
     * (most stars first, then newest).
     *
     * @param  Builder<Center>  $query
     * @return Builder<Center>
     */
    private function withTopRatings(Builder $query): Builder
    {
        return $query->withRatingStats()->with([
            'ratings' => fn ($ratings) => $ratings
                ->with('rater')
                ->orderByDesc('stars')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(self::RATINGS_LIMIT),
        ]);
    }
}
