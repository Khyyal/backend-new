<?php

namespace Modules\Billing\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Models\Subscription;
use Modules\Centers\Models\Center;
use Modules\Support\Support\ListSort;

/**
 * Every subscription across centers, newest first unless `sort` is given.
 */
class AdminSubscriptionQuery
{
    /**
     * Accepted `sort` values; a `-` prefix means descending.
     */
    public const SORTS = ['starts_at', '-starts_at', 'ends_at', '-ends_at'];

    /**
     * @param  array{center_id?: int, plan_id?: int, status?: string, from?: string, to?: string, sort?: string|null}  $filters
     * @return Builder<Subscription>
     */
    public function build(array $filters): Builder
    {
        return Subscription::query()
            ->with(['plan', 'subscribable'])
            ->when($filters['center_id'] ?? null, fn (Builder $query, int $centerId) => $query
                ->where('subscribable_type', (new Center)->getMorphClass())
                ->where('subscribable_id', $centerId))
            ->when($filters['plan_id'] ?? null, fn (Builder $query, int $planId) => $query->where('plan_id', $planId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('starts_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('starts_at', '<=', $date))
            ->tap(fn (Builder $query) => ListSort::apply($query, $filters['sort'] ?? null, [
                'starts_at' => 'starts_at',
                'ends_at' => 'ends_at',
            ], fn (Builder $query) => $query->orderByDesc('id')));
    }
}
