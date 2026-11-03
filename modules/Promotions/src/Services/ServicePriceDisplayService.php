<?php

namespace Modules\Promotions\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Promotions\Contracts\ServicePriceDisplay;
use Modules\Promotions\Data\DiscountableLine;
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Models\Promotion;

/**
 * Display ("from") pricing: automatic item offers applied to one unit at a
 * service's start price. Offers with a minimum spend or order scope depend on
 * the whole purchase and are ignored here.
 */
class ServicePriceDisplayService implements ServicePriceDisplay
{
    public function __construct(
        private readonly ActivePromotionRepository $promotions,
        private readonly DiscountEngine $engine,
        private readonly UsageLimitGuard $limits,
    ) {}

    public function forServices(Collection $services, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $display = [];

        foreach ($services->groupBy('center_id') as $centerId => $centerServices) {
            $promotions = $this->limits->available($this->promotions->liveFor((int) $centerId, $at), null)
                ->filter(fn (Promotion $promotion): bool => $promotion->scope === PromotionScope::Item && $promotion->min_subtotal_amount === null)
                ->values();

            if ($promotions->isEmpty()) {
                continue;
            }

            foreach ($centerServices as $service) {
                $unitPrice = $service->start_price;

                if ($unitPrice === null || $unitPrice <= 0) {
                    continue;
                }

                $result = $this->engine->apply(
                    [new DiscountableLine(0, (int) $centerId, (int) $service->getKey(), (int) $unitPrice)],
                    $promotions,
                );

                if ($result->totalDiscount() > 0) {
                    $display[(int) $service->getKey()] = [
                        'original' => (int) $unitPrice,
                        'discounted' => (int) $unitPrice - $result->totalDiscount(),
                        'discount_amount' => $result->totalDiscount(),
                        'badge' => $result->adjustments[0]->localizedLabel(),
                    ];
                }
            }
        }

        return $display;
    }
}
