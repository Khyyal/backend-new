<?php

namespace Modules\Clients\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Clients\Models\Client;
use Modules\Support\Support\ListSort;

/**
 * Every client across centers, filtered like the admin client list. Shared by
 * the list endpoint and the clients export. Soft-deleted clients are excluded.
 */
class AdminClientQuery
{
    /**
     * Accepted `sort` values; a `-` prefix means descending. `name` orders by first then last name.
     */
    public const SORTS = ['name', '-name', 'created_at', '-created_at', 'centers_count', '-centers_count', 'reservations_count', '-reservations_count'];

    /**
     * Newest first unless `sort` is given.
     *
     * @param  array{search?: string|null, city_id?: int|null, gender?: string|null, from?: string, to?: string, sort?: string|null}  $filters
     * @return Builder<Client>
     */
    public function build(array $filters): Builder
    {
        return Client::query()
            ->with('city')
            ->withCount(['centers', 'reservations', 'packages', 'memberships'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->search($term))
            ->when($filters['city_id'] ?? null, fn (Builder $query, int $cityId) => $query->where('city_id', $cityId))
            ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->where('gender', $gender))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->tap(fn (Builder $query) => ListSort::apply($query, $filters['sort'] ?? null, [
                'name' => ['first_name', 'last_name'],
                'created_at' => 'created_at',
                'centers_count' => 'centers_count',
                'reservations_count' => 'reservations_count',
            ], fn (Builder $query) => $query->orderByDesc('id')));
    }
}
