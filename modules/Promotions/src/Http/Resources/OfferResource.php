<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\PromotionScope;
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
    #[ResponseField('id', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseField('name', 'string', 'Name in the request locale (`Accept-Language`).', example: '15% off rides')]
    #[ResponseField('description', 'string', 'Description in the request locale.', nullable: true, example: 'Weekend riding discount.')]
    #[ResponseField('badge_label', 'string', 'Badge text in the request locale.', nullable: true, example: '-15%')]
    #[ResponseField('scope', 'string', '`item` discounts each matching cart line; `order` discounts the cart subtotal.', enum: PromotionScope::class, example: 'item')]
    #[ResponseField('discount_type', 'string', '`percentage` (value in bps), `fixed_amount` (halalas off) or `fixed_price` (line sells at value halalas; item scope only).', enum: DiscountType::class, example: 'percentage')]
    #[ResponseField('discount_value', 'integer', 'Basis points for `percentage` (1250 = 12.5%), otherwise halalas.', example: 1500)]
    #[ResponseField('max_discount_amount', 'integer', 'Cap on the discount in halalas; null means uncapped.', nullable: true, example: 5000)]
    #[ResponseField('min_subtotal_amount', 'integer', 'Minimum cart subtotal in halalas; null means no minimum.', nullable: true, example: 10000)]
    #[ResponseField('requires_coupon', 'boolean', 'True when the client must enter a coupon code at checkout.', example: false)]
    #[ResponseField('first_booking_only', 'boolean', 'Only for clients with no previous paid purchase.', example: false)]
    #[ResponseField('starts_at', 'string', 'Start (ISO-8601, UTC).', example: '2026-06-01T00:00:00+00:00')]
    #[ResponseField('ends_at', 'string', 'End (ISO-8601, UTC); null means open-ended.', nullable: true, example: '2026-08-31T23:59:59+00:00')]
    #[ResponseField('cover', 'string', 'Cover thumbnail URL; null when there is no cover.', required: false, nullable: true, example: 'https://cdn.khyyal.com/media/12/conversions/cover-cover-thumb.jpg')]
    #[ResponseField('center', 'object', 'Owning center; null for platform-wide offers.', required: false, nullable: true)]
    #[ResponseField('center.id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('center.name', 'string', 'Center name in the request locale.', example: 'Al Fursan Stables')]
    #[ResponseField('center.slug', 'string', 'Center slug.', example: 'al-fursan')]
    #[ResponseField('schedules', 'object[]', 'Weekly windows when the promotion is live; empty means the whole date range. Present when loaded.', required: false)]
    #[ResponseField('schedules[].day_of_week', 'integer', 'ISO weekday: 1 = Monday … 7 = Sunday.', example: 5)]
    #[ResponseField('schedules[].start_time', 'string', 'Window start, `HH:MM` (UTC).', example: '16:00')]
    #[ResponseField('schedules[].end_time', 'string', 'Window end, `HH:MM` (UTC). Earlier than start means it wraps past midnight.', example: '23:00')]
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
