<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Collection;
use Modules\Billing\Models\Plan;

class PlanFeatureLimitLookup
{
    public function getFeatures(Plan $plan): Collection
    {
        return $plan
            ->planFeatures()
            ->with('feature')
            ->get()
            ->map(function ($pf) {
                return (object) [
                    'key' => $pf->feature->key,
                    'name' => $pf->feature->name,
                    'is_quota' => (bool) $pf->feature->is_quota,
                    'enabled' => (bool) $pf->enabled,
                ];
            })
            ->values();
    }

    public function getLimitValue(Plan $plan, string $limitKey, int $default = 0): int
    {
        $match = $plan
            ->planLimits()
            ->whereHas('limit', function ($q) use ($limitKey) {
                $q->where('key', $limitKey);
            })
            ->with('limit')
            ->first();

        return $match ? (int) $match->value : $default;
    }

    public function getAllLimits(Plan $plan): Collection
    {
        return $plan
            ->planLimits()
            ->with('limit')
            ->get()
            ->map(function ($pl) {
                return (object) [
                    'key' => $pl->limit->key,
                    'name' => $pl->limit->name,
                    'value' => (int) $pl->value,
                ];
            })
            ->values();
    }

    public function planHasFeatureEnabled(Plan $plan, string $featureKey): bool
    {
        return $plan
            ->planFeatures()
            ->whereHas('feature', function ($q) use ($featureKey) {
                $q->where('key', $featureKey);
            })
            ->where('enabled', true)
            ->exists();
    }
}
