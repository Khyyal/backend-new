<?php

namespace Modules\Promotion\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Centers\Models\Center;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\PromotionStatus;
use Modules\Promotion\Models\Discount;
use Modules\Services\Models\Service;

class DiscountResolver
{
    public function __construct(
        private readonly DiscountEligibilityService $eligibility,
    ) {}

    /**
     * Eligible automatic discounts of the given center, ordered by id.
     *
     * @param  iterable<Service|int>  $services
     * @return array<int, Discount>
     */
    public function resolveAutomatic(
        Center $center,
        ?Model $user = null,
        float|int $subtotal = 0,
        iterable $services = [],
    ): array {
        return Discount::query()
            ->whereBelongsTo($center)
            ->where('application_method', ApplicationMethod::Automatic)
            ->where('status', PromotionStatus::Active)
            ->where('starts_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->with('services')
            ->orderBy('id', 'asc')
            ->get()
            ->filter(function (Discount $d) use ($user, $subtotal, $services) {
                return $this->eligibility->isEligible($d, $user, $subtotal, $services);
            })
            ->values()
            ->all();
    }
}
