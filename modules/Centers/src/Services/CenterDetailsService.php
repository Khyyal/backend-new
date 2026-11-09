<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Centers\Models\Center;
use Modules\Promotions\Contracts\ServicePriceDisplay;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Support\Haversine;

class CenterDetailsService
{
    public function __construct(
        private readonly ServicePriceDisplay $priceDisplay,
    ) {}

    /**
     * Find a visible center by slug with its active services overview.
     * Resort `start_price` is the minimum day price (add-ons excluded).
     * `distance_km` is set only when both coordinates are given.
     *
     * @throws ModelNotFoundException
     */
    public function findBySlug(string $slug, ?float $lat = null, ?float $lng = null): Center
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

        $center = $query->firstOrFail();

        $center->setAttribute('service_display_prices', $this->priceDisplay->forServices($center->services));

        return $center;
    }
}
