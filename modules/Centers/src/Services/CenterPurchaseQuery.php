<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Clients\Models\Client;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Support\Models\SystemUser;

/**
 * A center's purchases the user may see (see {@see CenterPurchaseVisibility}),
 * filtered like the center purchase list. Shared by the list endpoint and the
 * purchases export so both see the same rows.
 */
class CenterPurchaseQuery
{
    public function __construct(private readonly CenterPurchaseVisibility $visibility) {}

    /**
     * Every purchase of the center the user may see, unfiltered.
     *
     * @return Builder<Purchase>
     */
    public function visible(Center $center, User|SystemUser $user): Builder
    {
        $query = Purchase::query()
            ->where('merchant_type', $center->getMorphClass())
            ->where('merchant_id', $center->getKey());

        return $this->visibility->apply($query, $user, $center);
    }

    /**
     * Newest first, with a unique tie-breaker so exports can page through it.
     *
     * @param  array{status?: string, q?: string|null, service_id?: int, from?: string, to?: string}  $filters
     * @return Builder<Purchase>
     */
    public function build(Center $center, User|SystemUser $user, array $filters): Builder
    {
        return $this->visible($center, $user)
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.addcslashes(trim($filters['q']), '%_\\').'%';
                $query->whereHasMorph('buyer', [Client::class], fn (Builder $client) => $client
                    ->where('phone_number', 'like', $term)
                    ->orWhereRaw("concat(first_name, ' ', last_name) like ?", [$term]));
            })
            ->when(isset($filters['service_id']), fn (Builder $query) => $query->whereHas('items', fn (Builder $items) => $items->whereHasMorph(
                'purchasable',
                [Reservation::class, ClientPackage::class],
                fn (Builder $purchasable) => $purchasable->where('service_id', $filters['service_id']),
            )))
            ->when(isset($filters['from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->with(['buyer', 'latestPayment', 'fee', 'ledgerEntries'])
            ->withCount('items')
            ->latest()
            ->latest('id');
    }
}
