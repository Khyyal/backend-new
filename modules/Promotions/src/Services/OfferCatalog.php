<?php

namespace Modules\Promotions\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Promotions\Models\Promotion;

/**
 * "What is on offer right now", for client discovery. A promotion is running
 * when it is active and inside its date window; scheduled and coupon offers
 * count too, since the client surfaces say when and how they apply.
 */
class OfferCatalog
{
    public function __construct(
        private readonly UsageLimitGuard $limits,
    ) {}

    /**
     * Running promotions that are not used up and, with a client, that the
     * client may still use. First-booking offers are hidden unless the client
     * is known to be eligible.
     *
     * @return Collection<int, Promotion>
     */
    public function running(?int $clientId = null, ?int $centerId = null, ?CarbonInterface $at = null): Collection
    {
        $promotions = Promotion::query()
            ->activeAt($at ?? now())
            ->when($centerId !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('center_id')->orWhere('center_id', $centerId)))
            ->with(['targets', 'schedules', 'center', 'media'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return $this->limits->available($promotions, $clientId);
    }
}
