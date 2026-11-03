<?php

namespace Modules\Promotions\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\Client;
use Modules\Promotions\Models\Promotion;
use Modules\Purchase\Enums\PurchaseStatus;

/**
 * Advisory limit checks used while pricing. They read fresh counters (the
 * cached shortlist holds stale ones) but reserve nothing: RedemptionService
 * enforces the limits atomically when the purchase is created.
 */
class UsageLimitGuard
{
    /**
     * Drop promotions that are used up, or that this client may not use. With
     * no client, promotions that depend on who is buying are dropped as well.
     *
     * @param  Collection<int, Promotion>  $promotions
     * @return Collection<int, Promotion>
     */
    public function available(Collection $promotions, ?int $clientId): Collection
    {
        $limited = $promotions->filter(fn (Promotion $promotion): bool => $promotion->total_usage_limit !== null || $promotion->per_client_usage_limit !== null);
        $counts = $limited->isEmpty() ? collect() : Promotion::query()->whereKey($limited->modelKeys())->pluck('usage_count', 'id');
        $clientUses = $clientId !== null && $limited->isNotEmpty()
            ? DB::table('promotion_client_usages')->where('client_id', $clientId)->whereIn('promotion_id', $limited->modelKeys())->pluck('used_count', 'promotion_id')
            : collect();
        $hasPriorBookings = null;

        return $promotions->filter(function (Promotion $promotion) use ($counts, $clientUses, $clientId, &$hasPriorBookings): bool {
            if ($promotion->total_usage_limit !== null && (int) $counts->get($promotion->id, 0) >= $promotion->total_usage_limit) {
                return false;
            }

            if ($promotion->per_client_usage_limit !== null && (int) $clientUses->get($promotion->id, 0) >= $promotion->per_client_usage_limit) {
                return false;
            }

            if ($promotion->first_booking_only) {
                return $clientId !== null && ! ($hasPriorBookings ??= $this->hasPriorBookings($clientId));
            }

            return true;
        })->values();
    }

    public function totalLimitReached(Promotion $promotion): bool
    {
        return $promotion->total_usage_limit !== null
            && (int) DB::table('promotions')->where('id', $promotion->getKey())->value('usage_count') >= $promotion->total_usage_limit;
    }

    public function clientUses(string $table, string $column, int $id, int $clientId): int
    {
        return (int) DB::table($table)->where($column, $id)->where('client_id', $clientId)->value('used_count');
    }

    /**
     * "First booking" means no earlier purchase by the client that was not cancelled.
     */
    public function hasPriorBookings(int $clientId, ?int $exceptPurchaseId = null): bool
    {
        return DB::table('purchases')
            ->where('buyer_type', (new Client)->getMorphClass())
            ->where('buyer_id', $clientId)
            ->whereNull('deleted_at')
            ->where('status', '!=', PurchaseStatus::Cancelled->value)
            ->when($exceptPurchaseId !== null, fn ($query) => $query->where('id', '!=', $exceptPurchaseId))
            ->exists();
    }
}
