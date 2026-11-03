<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotions\Models\Coupon;

/**
 * @property Coupon $resource
 */
class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $coupon = $this->resource;

        return [
            'id' => $coupon->id,
            'promotion_id' => $coupon->promotion_id,
            'code' => $coupon->code,
            'usage_limit' => $coupon->usage_limit,
            'per_client_usage_limit' => $coupon->per_client_usage_limit,
            'used_count' => $coupon->used_count,
            'starts_at' => $coupon->starts_at?->toIso8601String(),
            'ends_at' => $coupon->ends_at?->toIso8601String(),
            'status' => $coupon->status->value,
            'is_assigned_only' => $coupon->is_assigned_only,
            'batch_id' => $coupon->batch_id,
            'assigned_clients_count' => $this->whenCounted('assignedClients'),
            'created_at' => $coupon->created_at?->toIso8601String(),
        ];
    }
}
