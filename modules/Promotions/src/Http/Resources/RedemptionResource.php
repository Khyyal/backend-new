<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotions\Models\PromotionRedemption;

/**
 * @property PromotionRedemption $resource
 */
class RedemptionResource extends JsonResource
{
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
