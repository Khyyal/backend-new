<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Models\Coupon;

/**
 * @property Coupon $resource
 */
class CouponResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Coupon ID.', example: 41)]
    #[ResponseField('promotion_id', 'integer', 'Owning promotion ID.', example: 12)]
    #[ResponseField('code', 'string', 'Upper-case coupon code.', example: 'SUMMER25')]
    #[ResponseField('usage_limit', 'integer', 'Total redemptions allowed. Null means unlimited.', nullable: true, example: 500)]
    #[ResponseField('per_client_usage_limit', 'integer', 'Redemptions allowed per client. Null means unlimited.', nullable: true, example: 1)]
    #[ResponseField('used_count', 'integer', 'Redemptions so far (released ones excluded).', example: 37)]
    #[ResponseField('starts_at', 'string', 'Validity start (ISO-8601). Null falls back to the promotion window.', nullable: true, example: '2026-06-01T00:00:00+00:00')]
    #[ResponseField('ends_at', 'string', 'Validity end (ISO-8601). Null falls back to the promotion window.', nullable: true, example: '2026-06-30T23:59:59+00:00')]
    #[ResponseField('status', 'string', 'Coupon status.', enum: CouponStatus::class, example: 'active')]
    #[ResponseField('is_assigned_only', 'boolean', 'When true only assigned clients can redeem it.', example: false)]
    #[ResponseField('batch_id', 'string', 'Generation batch UUID for bulk-generated codes; null for manual codes.', nullable: true, example: '01J0Z8Q4M6T2W9A7C5V3B1N8XK')]
    #[ResponseField('assigned_clients_count', 'integer', 'Number of assigned clients. Present on list responses.', required: false, example: 0)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
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
