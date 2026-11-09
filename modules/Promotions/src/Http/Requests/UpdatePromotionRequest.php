<?php

namespace Modules\Promotions\Http\Requests;

use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Enums\PromotionStacking;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Models\Promotion;

#[BodyParam('name', 'object', 'Promotion name, keyed by locale.', required: false, example: ['en' => '15% off rides', 'ar' => 'خصم 15% على الركوب'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, example: '15% off rides')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: false, nullable: true, example: 'خصم 15% على الركوب')]
#[BodyParam('description', 'object', 'Description, keyed by locale.', required: false, nullable: true, example: ['en' => 'Weekend riding discount.', 'ar' => 'خصم ركوب نهاية الأسبوع.'])]
#[BodyParam('description.en', 'string', 'English description (max 2000).', required: false, nullable: true, example: 'Weekend riding discount.')]
#[BodyParam('description.ar', 'string', 'Arabic description (max 2000).', required: false, nullable: true, example: 'خصم ركوب نهاية الأسبوع.')]
#[BodyParam('badge_label', 'object', 'Short card badge, keyed by locale.', required: false, nullable: true, example: ['en' => '-15%', 'ar' => '-15%'])]
#[BodyParam('badge_label.en', 'string', 'English badge (max 60).', required: false, nullable: true, example: '-15%')]
#[BodyParam('badge_label.ar', 'string', 'Arabic badge (max 60).', required: false, nullable: true, example: '-15%')]
#[BodyParam('cover', 'string', 'UUID of a temporary upload from `POST /api/v1/media`; null removes the cover.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('campaign_id', 'integer', 'Existing campaign ID. **Admin API only**; prohibited on the center API.', required: false, nullable: true, example: 3)]
#[BodyParam('center_id', 'integer', 'Owning center (not deleted); null makes it platform-wide. **Admin API only**; on the center API the route center is used.', required: false, nullable: true, example: 8)]
#[BodyParam('trigger', 'string', 'Prohibited on update: the trigger is fixed at creation.', required: false, enum: PromotionTrigger::class, example: 'No-example')]
#[BodyParam('scope', 'string', '`item` discounts matching cart lines; `order` discounts the subtotal.', required: false, enum: PromotionScope::class, example: 'item')]
#[BodyParam('discount_type', 'string', '`fixed_price` is only allowed with `item` scope.', required: false, enum: DiscountType::class, example: 'percentage')]
#[BodyParam('discount_value', 'integer', 'Basis points for `percentage` (1-10000, 1250 = 12.5%), otherwise halalas (min 1).', required: false, example: 1500)]
#[BodyParam('max_discount_amount', 'integer', 'Cap in halalas (min 1); null for uncapped.', required: false, nullable: true, example: 5000)]
#[BodyParam('min_subtotal_amount', 'integer', 'Minimum cart subtotal in halalas (min 0); null for none.', required: false, nullable: true, example: 10000)]
#[BodyParam('starts_at', 'string', 'Start date-time.', required: false, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'End date-time; must be after `starts_at` (or the stored start on update). Null for open-ended.', required: false, nullable: true, example: '2026-08-31T23:59:59Z')]
#[BodyParam('priority', 'integer', 'Tie-break between exclusive promotions, -1000 … 1000. Default 0.', required: false, example: 0)]
#[BodyParam('stacking', 'string', 'Default `stackable`.', required: false, enum: PromotionStacking::class, example: 'stackable')]
#[BodyParam('total_usage_limit', 'integer', 'Total redemptions allowed (min 1); null for unlimited.', required: false, nullable: true, example: 1000)]
#[BodyParam('per_client_usage_limit', 'integer', 'Redemptions per client (min 1); null for unlimited.', required: false, nullable: true, example: 1)]
#[BodyParam('first_booking_only', 'boolean', 'Only for clients with no previous paid purchase. Default false.', required: false, example: false)]
#[BodyParam('funded_by', 'string', 'Who bears the discount. Default `platform`. **Admin API only**; center promotions are always center-funded.', required: false, enum: FundedBy::class, example: 'shared')]
#[BodyParam('center_share_bps', 'integer', 'Center share in basis points (0-10000). Required when `funded_by` is `shared`. **Admin API only**.', required: false, nullable: true, example: 5000)]
class UpdatePromotionRequest extends StorePromotionRequest
{
    protected bool $isUpdate = true;

    protected function currentPromotion(): ?Promotion
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion ? $promotion : null;
    }
}
