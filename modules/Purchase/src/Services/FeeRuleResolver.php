<?php

namespace Modules\Purchase\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Models\FeeRule;

/**
 * Finds the fee rule for a center's purchase on a channel. The first active
 * rule wins, from the most specific to the least:
 * the center's rule for the channel, the platform rule for the channel,
 * the center's fallback, then the platform fallback.
 */
class FeeRuleResolver
{
    public function resolve(Center $center, ?PurchaseChannel $channel): ?FeeRule
    {
        $rules = FeeRule::query()
            ->active()
            ->where(fn (Builder $query) => $query->whereNull('center_id')->orWhere('center_id', $center->getKey()))
            ->where(fn (Builder $query) => $query->whereNull('channel')->when($channel !== null, fn (Builder $query) => $query->orWhere('channel', $channel)))
            ->get();

        return $rules->sortBy(fn (FeeRule $rule): int => match (true) {
            $rule->channel !== null && $rule->center_id !== null => 0,
            $rule->channel !== null => 1,
            $rule->center_id !== null => 2,
            default => 3,
        })->first();
    }
}
