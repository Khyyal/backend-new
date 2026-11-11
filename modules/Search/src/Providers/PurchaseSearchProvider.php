<?php

namespace Modules\Search\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Purchase\Models\Purchase;
use Modules\Search\Contracts\GlobalSearchProvider;
use Modules\Search\Data\SearchHit;
use Modules\Search\Support\SearchTerm;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

/**
 * Client purchases at centers (as in AdminPurchaseQuery) by public code (exact, listed first) or by the buyer's name/phone.
 */
class PurchaseSearchProvider implements GlobalSearchProvider
{
    public function key(): string
    {
        return 'purchases';
    }

    public function authorize(SystemUser $user): bool
    {
        return $user->hasPermissionTo(SystemPermission::ViewAnyPurchases);
    }

    public function search(SearchTerm $term, int $limit, string $locale): Collection
    {
        $isCode = $term->looksLikeCode();

        return Purchase::query()
            ->where('buyer_type', (new Client)->getMorphClass())
            ->where('merchant_type', (new Center)->getMorphClass())
            ->where(fn (Builder $query) => $query
                ->whereHasMorph('buyer', [Client::class], fn (Builder $client) => $client->search($term->value))
                ->when($isCode, fn (Builder $query) => $query->orWhere->whereCode($term->code())))
            ->when($isCode, fn (Builder $query) => $query->orderByRaw('case when code = ? then 0 else 1 end', [$term->code()]))
            ->latest('id')
            ->limit($limit)
            ->with(['buyer', 'merchant'])
            ->get()
            ->map(function (Purchase $purchase): SearchHit {
                /** @var Client|null $buyer */
                $buyer = $purchase->buyer;
                /** @var Center|null $center */
                $center = $purchase->merchant;

                return new SearchHit(
                    id: $purchase->id,
                    title: $buyer?->full_name ?? $purchase->code,
                    subtitle: $center?->name,
                    code: $purchase->code,
                    centerId: $purchase->merchant_id,
                    meta: ['status' => $purchase->status?->value, 'total_amount' => $purchase->total_amount],
                );
            });
    }
}
