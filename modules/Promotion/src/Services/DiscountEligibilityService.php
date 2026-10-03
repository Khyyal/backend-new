<?php

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;
use Modules\Promotion\Models\Discountable;

class DiscountEligibilityService
{
    public function isEligible(
        Discount $discount,
        ?Model $user = null,
        float|int $subtotal = 0,
        iterable $items = [],
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
            if (! is_countable($items) || count($items) === 0) {
                return false;
            }

            $attachedKeys = $discount->discountables
                ->map(fn (Discountable $d) => $d->discountable_type.'|'.$d->discountable_id)
                ->unique()
                ->values();

            $itemKeys = collect($items)
                ->map(fn (Model $m) => $this->modelKey($m))
                ->values();

            if ($attachedKeys->intersect($itemKeys)->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    private function modelKey(Model $model): string
    {
        return $model->getMorphClass().'|'.$model->getKey();
    }
}
