<?php

namespace Modules\Billing\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Models\Subscription;
use Modules\Centers\Models\Center;

/**
 * Every subscription across centers, newest first.
 */
class AdminSubscriptionQuery
{
    /**
     * @param  array{center_id?: int, plan_id?: int, status?: string, from?: string, to?: string}  $filters
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
            ->orderByDesc('id');
    }
}
