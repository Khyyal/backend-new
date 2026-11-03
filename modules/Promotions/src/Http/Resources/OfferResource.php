<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Models\Promotion;

/**
 * A running promotion as clients see it. Never exposes coupon codes or usage
 * counters; `requires_coupon` tells the app a code is needed.
 *
 * @property Promotion $resource
 */
class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $promotion = $this->resource;

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'description' => $promotion->description,
            'badge_label' => $promotion->badge_label,
            'scope' => $promotion->scope->value,
            'discount_type' => $promotion->discount_type->value,
            'discount_value' => $promotion->discount_value,
            'max_discount_amount' => $promotion->max_discount_amount,
            'min_subtotal_amount' => $promotion->min_subtotal_amount,
            'requires_coupon' => $promotion->trigger === PromotionTrigger::Coupon,
            'first_booking_only' => $promotion->first_booking_only,
            'starts_at' => $promotion->starts_at?->toIso8601String(),
            'ends_at' => $promotion->ends_at?->toIso8601String(),
            'cover' => $this->whenLoaded('media', fn () => $promotion->getFirstMedia('cover')?->getFullUrl('cover-thumb')),
            'center' => $this->whenLoaded('center', fn () => $promotion->center === null ? null : [
                'id' => $promotion->center->id,
                'name' => $promotion->center->name,
                'slug' => $promotion->center->slug,
            ]),
            'schedules' => $this->whenLoaded('schedules', fn () => PromotionResource::schedules($promotion)),
        ];
    }
}
