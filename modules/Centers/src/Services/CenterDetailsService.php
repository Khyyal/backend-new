<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Support\Haversine;

class CenterDetailsService
{
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
                    ->withMin(['priceOptions as start_price' => fn ($prices) => $prices->where(
                        fn ($scope) => $scope->whereNotNull('price_options.quantity')
                            ->orWhere('services.type', '!=', ServiceType::Resort->value)
                    ),
                    ], 'price')
                    ->with('media')
                    ->orderBy('id'),
            ]);

        if ($lat !== null && $lng !== null) {
            $query->selectRaw(
                Haversine::sql('centers.lat', 'centers.lng').' as distance_km',
                Haversine::bindings($lat, $lng),
            );
        }

        return $query->firstOrFail();
    }
}
