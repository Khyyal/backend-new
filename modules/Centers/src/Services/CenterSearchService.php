<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Enums\CenterOrder;
use Modules\Centers\Models\Center;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CenterSearchService
{
    /**
     * Bump this when the query/filter/sort shape changes, so old cache
     * entries are never read as if they matched the new shape.
     */
    private const CACHE_VERSION = 'v1';

    private const CACHE_TTL_MINUTES = 10;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);

        return $this->cacheRepository()->remember(
            $this->cacheKey($filters, $page),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->query($filters)->paginate(15, ['*'], 'page', $page),
        );
    }

    /**
     * Tags need Redis/Memcached/DynamoDB; falls back to a plain (untagged,
     * TTL-only) cache on stores that don't support them, e.g. the
     * `array`/`database` drivers used in tests or a non-Redis environment.
     */
    private function cacheRepository(): mixed
    {
        return Center::supportsCacheTags() ? Cache::tags(['centers']) : Cache::store();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): QueryBuilder
    {
        $order = isset($filters['order']) ? CenterOrder::from($filters['order']) : CenterOrder::MostPopular;

        $syntheticRequest = Request::create('/', 'GET', [
            'filter' => array_filter(
                Arr::only($filters, ['city_id', 'search', 'services_types', 'tags', 'bounds']),
                fn ($value) => $value !== null,
            ),
            'sort' => $order->value,
        ]);

        return QueryBuilder::for(
            Center::query()->visible()->withRatingStats()->with(['city', 'tags', 'media']),
            $syntheticRequest,
        )
            ->allowedFilters(
                AllowedFilter::exact('city_id'),
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::callback('services_types', fn (Builder $query, $value) => $query->whereHas(
                    'services',
                    fn (Builder $servicesQuery) => $servicesQuery->whereIn('type', (array) $value)
                )),
                AllowedFilter::callback('tags', fn (Builder $query, $value) => $query->whereHas(
                    'tags',
                    fn (Builder $tagsQuery) => $tagsQuery->whereIn('tags.id', (array) $value)
                )),
                AllowedFilter::callback('bounds', fn (Builder $query, $value) => $query
                    ->whereBetween('lat', [$value['south'], $value['north']])
                    ->whereBetween('lng', [$value['west'], $value['east']])),
            )
            ->allowedSorts(
                AllowedSort::callback(
                    CenterOrder::MostPopular->value,
                    fn (Builder $query) => $query->orderByDesc('points'),
                ),
                AllowedSort::callback(
                    CenterOrder::Nearest->value,
                    fn (Builder $query) => $query->orderByRaw(...$this->nearestOrderRaw($filters)),
                ),
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: array<int, float>}
     */
    private function nearestOrderRaw(array $filters): array
    {
        $lat = (float) $filters['lat'];
        $lng = (float) $filters['lng'];

        if (DB::connection()->getDriverName() === 'mysql') {
            // Geodesically accurate (meters). MySQL-only: POINT()/ST_Distance_Sphere
            // aren't available on the sqlite connection the test suite runs on.
            return ['ST_Distance_Sphere(POINT(lng, lat), POINT(?, ?)) asc', [$lng, $lat]];
        }

        // Squared planar distance: no sqrt/trig needed since it's only used
        // for ordering (sqrt is monotonic), portable across every driver.
        return ['(lat - ?) * (lat - ?) + (lng - ?) * (lng - ?) asc', [$lat, $lat, $lng, $lng]];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cacheKey(array $filters, int $page): string
    {
        $normalized = Arr::except($filters, ['page']);

        foreach (['services_types', 'tags'] as $listKey) {
            if (isset($normalized[$listKey]) && is_array($normalized[$listKey])) {
                sort($normalized[$listKey]);
            }
        }

        foreach (['lat', 'lng'] as $coordKey) {
            if (isset($normalized[$coordKey])) {
                // Round so almost-identical locations share a cache entry.
                $normalized[$coordKey] = round((float) $normalized[$coordKey], 3);
            }
        }

        ksort($normalized);

        $hash = md5(json_encode($normalized, JSON_THROW_ON_ERROR));

        return 'centers:search:'.self::CACHE_VERSION.':'.$hash.':page:'.$page;
    }
}
