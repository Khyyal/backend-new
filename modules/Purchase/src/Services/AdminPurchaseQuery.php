<?php

namespace Modules\Purchase\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Support\Support\ListSort;

/**
 * Platform-wide client purchases filtered like the admin purchase list, shared
 * by the list endpoint and the purchases export so both see the same rows.
 */
class AdminPurchaseQuery
{
    /**
     * Accepted `sort` values; a `-` prefix means descending.
     */
    public const SORTS = ['created_at', '-created_at', 'total_amount', '-total_amount'];

    /**
     * Newest first unless `sort` is given, always with a unique tie-breaker so exports can page through it.
     *
     * @param  array{center_id?: int, client_id?: int, q?: string|null, status?: string, source?: string, channel?: string, payment_method?: string, payment_status?: string, service_id?: int, from?: string, to?: string, sort?: string|null}  $filters
     * @return Builder<Purchase>
     */
    public function build(array $filters): Builder
    {
        return Purchase::query()
            ->where('buyer_type', (new Client)->getMorphClass())
            ->where('merchant_type', (new Center)->getMorphClass())
            ->when($filters['center_id'] ?? null, fn (Builder $query, int $id) => $query->where('merchant_id', $id))
            ->when($filters['client_id'] ?? null, fn (Builder $query, int $id) => $query->where('buyer_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['source'] ?? null, fn (Builder $query, string $value) => $query->where('source', $value))
            ->when($filters['channel'] ?? null, fn (Builder $query, string $value) => $query->where('channel', $value))
            ->when(filled($filters['q'] ?? null), fn (Builder $query) => $query->whereHasMorph(
                'buyer',
                [Client::class],
                fn (Builder $client) => $client->search($filters['q']),
            ))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $value) => $query->whereHas('latestPayment', fn (Builder $payment) => $payment->where('method', $value)))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $value) => $query->whereHas('latestPayment', fn (Builder $payment) => $payment->where('status', $value)))
            ->when($filters['service_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('items', fn (Builder $items) => $items->whereHasMorph(
                'purchasable',
                [Reservation::class, ClientPackage::class],
                fn (Builder $purchasable) => $purchasable->where('service_id', $id),
            )))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->with(['buyer', 'merchant', 'latestPayment', 'fee'])
            ->withCount('items')
            ->tap(fn (Builder $query) => ListSort::apply($query, $filters['sort'] ?? null, [
                'created_at' => 'created_at',
                'total_amount' => 'total_amount',
            ], fn (Builder $query) => $query->latest()->latest('id')));
    }
}
