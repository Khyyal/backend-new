<?php

namespace Modules\Clients\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Modules\Centers\Enums\CenterOrder;
use Modules\Centers\Models\Center;

class CenterSearchService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);

        return Cache::remember(
            $this->cacheKey($filters, $page),
            now()->addMinutes(10),
            fn () => $this->query($filters)->paginate(15, ['*'], 'page', $page),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): Builder
    {
        $order = isset($filters['order']) ? CenterOrder::from($filters['order']) : CenterOrder::MostPopular;

        return Center::query()
            ->visible()
            ->withRatingStats()
            ->with(['city', 'tags', 'media'])
            ->when($filters['city_id'] ?? null, fn (Builder $query, $cityId) => $query->where('city_id', $cityId))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['services_types'] ?? null, fn (Builder $query, $types) => $query->whereHas(
                'services',
                fn (Builder $servicesQuery) => $servicesQuery->whereIn('type', $types)
            ))
            ->when($filters['tags'] ?? null, fn (Builder $query, $tagIds) => $query->whereHas(
                'tags',
                fn (Builder $tagsQuery) => $tagsQuery->whereIn('tags.id', $tagIds)
            ))
            ->when($filters['bounds'] ?? null, fn (Builder $query, $bounds) => $query
                ->whereBetween('lat', [$bounds['south'], $bounds['north']])
                ->whereBetween('lng', [$bounds['west'], $bounds['east']]))
            ->when(
                $order === CenterOrder::Nearest,
                fn (Builder $query) => $query->orderByRaw(
                    '(lat - ?) * (lat - ?) + (lng - ?) * (lng - ?) asc',
                    [$filters['lat'], $filters['lat'], $filters['lng'], $filters['lng']],
                ),
                fn (Builder $query) => $query->orderByDesc('points'),
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cacheKey(array $filters, int $page): string
    {
        $normalized = $filters;
        unset($normalized['page']);

        foreach (['services_types', 'tags'] as $listKey) {
            if (isset($normalized[$listKey])) {
                sort($normalized[$listKey]);
            }
        }

        ksort($normalized);

        return 'clients.centers.index.'.$page.'.'.md5(json_encode($normalized));
    }
}
