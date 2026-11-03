<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Models\PromotionSchedule;
use Modules\Promotions\Models\PromotionTarget;
use Modules\Support\Http\Resources\MediaResource;

/**
 * A promotion as managed on the admin and center APIs. Amounts are minor
 * units; a percentage `discount_value` is in basis points.
 *
 * @property Promotion $resource
 */
class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $promotion = $this->resource;

        return [
            'id' => $promotion->id,
            'campaign_id' => $promotion->campaign_id,
            'center_id' => $promotion->center_id,
            'name' => $promotion->getTranslations('name'),
            'description' => $promotion->getTranslations('description'),
            'badge_label' => $promotion->getTranslations('badge_label'),
            'trigger' => $promotion->trigger->value,
            'scope' => $promotion->scope->value,
            'discount_type' => $promotion->discount_type->value,
            'discount_value' => $promotion->discount_value,
            'max_discount_amount' => $promotion->max_discount_amount,
            'min_subtotal_amount' => $promotion->min_subtotal_amount,
            'starts_at' => $promotion->starts_at?->toIso8601String(),
            'ends_at' => $promotion->ends_at?->toIso8601String(),
            'status' => $promotion->status->value,
            'is_live' => $promotion->isActiveAt(now()) && $promotion->isScheduledAt(now()),
            'priority' => $promotion->priority,
            'stacking' => $promotion->stacking->value,
            'funded_by' => $promotion->funded_by->value,
            'center_share_bps' => $promotion->center_share_bps,
            'total_usage_limit' => $promotion->total_usage_limit,
            'per_client_usage_limit' => $promotion->per_client_usage_limit,
            'usage_count' => $promotion->usage_count,
            'first_booking_only' => $promotion->first_booking_only,
            'targets' => $this->whenLoaded('targets', fn () => $promotion->targets->map(fn (PromotionTarget $target): array => [
                'type' => $target->target_type->value,
                'id' => $target->target_id,
                'mode' => $target->mode->value,
            ])->values()),
            'schedules' => $this->whenLoaded('schedules', fn () => self::schedules($promotion)),
            'cover' => $this->whenLoaded('media', fn () => ($media = $promotion->getFirstMedia('cover')) !== null ? new MediaResource($media) : null),
            'coupons_count' => $this->whenCounted('coupons'),
            'created_at' => $promotion->created_at?->toIso8601String(),
            'updated_at' => $promotion->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{day_of_week: int, start_time: string, end_time: string}>
     */
    public static function schedules(Promotion $promotion): array
    {
        return $promotion->schedules->map(fn (PromotionSchedule $schedule): array => [
            'day_of_week' => $schedule->day_of_week,
            'start_time' => substr($schedule->start_time, 0, 5),
            'end_time' => substr($schedule->end_time, 0, 5),
        ])->values()->all();
    }
}
