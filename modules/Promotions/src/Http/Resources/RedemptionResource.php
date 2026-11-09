<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\RedemptionStatus;
use Modules\Promotions\Models\PromotionRedemption;

/**
 * @property PromotionRedemption $resource
 */
class RedemptionResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Redemption ID.', example: 908)]
    #[ResponseField('promotion_id', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseField('coupon_id', 'integer', 'Coupon ID used; null for automatic promotions.', nullable: true, example: 41)]
    #[ResponseField('coupon_code', 'string', 'Coupon code used; null for automatic promotions.', nullable: true, example: 'SUMMER25')]
    #[ResponseField('purchase_id', 'integer', 'Purchase the discount was applied to.', example: 5521)]
    #[ResponseField('client_id', 'integer', 'Client who redeemed it.', example: 77)]
    #[ResponseField('discount_amount', 'integer', 'Discount granted, in halalas.', example: 2500)]
    #[ResponseField('funded_by', 'string', 'Who bears the discount.', enum: FundedBy::class, example: 'shared')]
    #[ResponseField('center_share_amount', 'integer', 'Part of the discount borne by the center, in halalas.', example: 1250)]
    #[ResponseField('status', 'string', '`released` when the purchase was cancelled or refunded.', enum: RedemptionStatus::class, example: 'applied')]
    #[ResponseField('released_at', 'string', 'Release time (ISO-8601); null while applied.', nullable: true, example: 'No-example')]
    #[ResponseField('release_reason', 'string', 'Why it was released; null while applied.', nullable: true, enum: ['purchase_cancelled', 'payment_refunded'], example: 'No-example')]
    #[ResponseField('created_at', 'string', 'Redemption time (ISO-8601).', example: '2026-06-02T14:10:00+00:00')]
    public function toArray(Request $request): array
    {
        $redemption = $this->resource;

        return [
            'id' => $redemption->id,
            'promotion_id' => $redemption->promotion_id,
            'coupon_id' => $redemption->coupon_id,
            'coupon_code' => $this->whenLoaded('coupon', fn () => $redemption->coupon?->code),
            'purchase_id' => $redemption->purchase_id,
            'client_id' => $redemption->client_id,
            'discount_amount' => $redemption->discount_amount,
            'funded_by' => $redemption->funded_by->value,
            'center_share_amount' => $redemption->center_share_amount,
            'status' => $redemption->status->value,
            'released_at' => $redemption->released_at?->toIso8601String(),
            'release_reason' => $redemption->release_reason,
            'created_at' => $redemption->created_at?->toIso8601String(),
        ];
    }
}
