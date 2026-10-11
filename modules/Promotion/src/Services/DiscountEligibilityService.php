<?php

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;
use Modules\Services\Models\Service;

class DiscountEligibilityService
{
    /**
     * @param  iterable<Service|int>  $services  Services (or service ids) being purchased.
     */
    public function isEligible(
        Discount $discount,
        ?Model $user = null,
        float|int $subtotal = 0,
        iterable $services = [],
    ): bool {
        if ($discount->status !== PromotionStatus::Active) {
            return false;
        }

        $now = now();

        if ($discount->starts_at->gt($now)) {
            return false;
        }

        if ($discount->ends_at !== null && $discount->ends_at->lt($now)) {
            return false;
        }

        if ($discount->minimum_amount !== null && (float) $subtotal < (float) $discount->minimum_amount) {
            return false;
        }

        if ($discount->usage_limit !== null && $discount->redemptions()->count() >= $discount->usage_limit) {
            return false;
        }

        if ($discount->usage_limit_per_customer !== null && $user !== null) {
            $count = $discount->redemptions()
                ->where('used_by_type', $user->getMorphClass())
                ->where('used_by_id', $user->getKey())
                ->count();

            if ($count >= $discount->usage_limit_per_customer) {
                return false;
            }
        }

        if ($discount->scope === DiscountScope::SpecificItems) {
            $serviceIds = collect($services)
                ->map(fn (Service|int $service): int => $service instanceof Service ? $service->getKey() : $service);

            if ($serviceIds->intersect($discount->services->modelKeys())->isEmpty()) {
                return false;
            }
        }

        return true;
    }
}
