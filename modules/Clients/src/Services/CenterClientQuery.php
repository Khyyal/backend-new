<?php

namespace Modules\Clients\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PurchaseStatus;

/**
 * A center's clients: those that belong to it (center_client) or have a
 * confirmed or completed purchase from it, with their purchase stats. Shared
 * by the center client list and the center clients export.
 */
class CenterClientQuery
{
    /**
     * Most recent buyer first, then clients without purchases (newest first).
     *
     * @param  array{q?: string|null, from?: string, to?: string}  $filters  `from`/`to` bound the last purchase date.
     */
    public function build(Center $center, array $filters = []): Builder
    {
        $purchaseStats = DB::table('purchases')
            ->where('buyer_type', (new Client)->getMorphClass())
            ->where('merchant_type', $center->getMorphClass())
            ->where('merchant_id', $center->getKey())
            ->whereIn('status', [PurchaseStatus::Confirmed->value, PurchaseStatus::Completed->value])
            ->whereNull('deleted_at')
            ->groupBy('buyer_id')
            ->select([
                'buyer_id',
                DB::raw('min(created_at) as first_purchase_at'),
                DB::raw('max(created_at) as last_purchase_at'),
                DB::raw('count(id) as purchases_count'),
            ]);

        return DB::table('clients')
            ->leftJoinSub($purchaseStats, 'stats', 'stats.buyer_id', '=', 'clients.id')
            ->whereNull('clients.deleted_at')
            ->where(fn (Builder $query) => $query
                ->whereNotNull('stats.buyer_id')
                ->orWhereExists(fn (Builder $membership) => $membership
                    ->from('center_client')
                    ->whereColumn('center_client.client_id', 'clients.id')
                    ->where('center_client.center_id', $center->getKey())))
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                foreach (preg_split('/\s+/', trim($filters['q']), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $word = '%'.addcslashes($word, '%_\\').'%';
                    $query->where(fn (Builder $match) => $match
                        ->where('clients.first_name', 'like', $word)
                        ->orWhere('clients.last_name', 'like', $word)
                        ->orWhere('clients.phone_number', 'like', $word));
                }
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('stats.last_purchase_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('stats.last_purchase_at', '<=', $date))
            ->select([
                'clients.id',
                'clients.first_name',
                'clients.last_name',
                'clients.phone_number',
                'stats.first_purchase_at',
                'stats.last_purchase_at',
                DB::raw('coalesce(stats.purchases_count, 0) as purchases_count'),
            ])
            ->orderByRaw('stats.last_purchase_at is null')
            ->orderByDesc('stats.last_purchase_at')
            ->orderByDesc('clients.id');
    }
}
