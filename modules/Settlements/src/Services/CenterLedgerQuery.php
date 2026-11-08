<?php

namespace Modules\Settlements\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Models\Center;
use Modules\Settlements\Models\CenterLedgerEntry;

/**
 * A center's ledger entries filtered like the admin ledger list. Shared by the
 * list endpoint and the ledger export.
 */
class CenterLedgerQuery
{
    /**
     * Newest first.
     *
     * @param  array{type?: string, paid_out?: bool|string, from?: string, to?: string}  $filters
     * @return Builder<CenterLedgerEntry>
     */
    public function build(Center $center, array $filters = []): Builder
    {
        return CenterLedgerEntry::query()
            ->where('center_id', $center->id)
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(isset($filters['paid_out']), fn (Builder $query) => filter_var($filters['paid_out'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereNotNull('payout_id')
                : $query->whereNull('payout_id'))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id');
    }
}
