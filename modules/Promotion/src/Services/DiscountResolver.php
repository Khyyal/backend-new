<?php

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;

class DiscountResolver
{
    public function __construct(
        private readonly DiscountEligibilityService $eligibility,
    ) {}

    public function resolveAutomatic(
        ?Model $owner = null,
        ?Model $user = null,
        float|int $subtotal = 0,
        iterable $items = [],
    ): array {
        $query = Discount::query()
            ->where('application_method', ApplicationMethod::Automatic)
            ->where('status', PromotionStatus::Active)
            ->where('starts_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->with('discountables')
            ->orderBy('id', 'asc');

        if ($owner !== null) {
            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        }

        return $query
            ->get()
            ->filter(function (Discount $d) use ($user, $subtotal, $items) {
                return $this->eligibility->isEligible($d, $user, $subtotal, $items);
            })
            ->values()
            ->all();
    }
}
