<?php

namespace Modules\Support\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies an admin list `sort` value: a key ascending, or `-key` descending.
 * The primary key is added in the same direction as a tiebreaker so pages and
 * exports stay stable. An empty or unknown key falls back to the list's
 * default order (requests validate the key, so unknown only happens in code).
 */
final class ListSort
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string|list<string>>  $columns  Sort key => column(s) it orders by.
     * @param  Closure(Builder<TModel>): mixed  $default
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?string $sort, array $columns, Closure $default): Builder
    {
        $key = ltrim((string) $sort, '-');

        if (! isset($columns[$key])) {
            $default($query);

            return $query;
        }

        $direction = str_starts_with((string) $sort, '-') ? 'desc' : 'asc';

        foreach ((array) $columns[$key] as $column) {
            $query->orderBy($column, $direction);
        }

        return $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
    }
}
