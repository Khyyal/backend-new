<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Enums\PromotionStacking;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Enums\TargetMode;
use Modules\Promotions\Enums\TargetType;
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
    #[ResponseField('id', 'integer', 'Promotion ID.', example: 12)]
    #[ResponseField('campaign_id', 'integer', 'Campaign it belongs to; null when standalone.', nullable: true, example: 3)]
    #[ResponseField('center_id', 'integer', 'Owning center; null for platform-wide promotions.', nullable: true, example: 8)]
    #[ResponseField('name', 'object', 'Promotion name. Translations keyed by locale; empty object when unset.')]
    #[ResponseField('name.en', 'string', 'English text.', required: false, example: '15% off rides')]
    #[ResponseField('name.ar', 'string', 'Arabic text.', required: false, example: 'خصم 15% على الركوب')]
    #[ResponseField('description', 'object', 'Promotion description. Translations keyed by locale; empty object when unset.')]
    #[ResponseField('description.en', 'string', 'English text.', required: false, example: 'Weekend riding discount.')]
    #[ResponseField('description.ar', 'string', 'Arabic text.', required: false, example: 'خصم ركوب نهاية الأسبوع.')]
    #[ResponseField('badge_label', 'object', 'Short badge shown on cards (max 60 chars). Translations keyed by locale; empty object when unset.')]
    #[ResponseField('badge_label.en', 'string', 'English text.', required: false, example: '-15%')]
    #[ResponseField('badge_label.ar', 'string', 'Arabic text.', required: false, example: '-15%')]
    #[ResponseField('trigger', 'string', '`automatic` applies by itself; `coupon` needs a coupon code.', enum: PromotionTrigger::class, example: 'automatic')]
    #[ResponseField('scope', 'string', '`item` discounts each matching cart line; `order` discounts the cart subtotal.', enum: PromotionScope::class, example: 'item')]
    #[ResponseField('discount_type', 'string', '`percentage` (value in bps), `fixed_amount` (halalas off) or `fixed_price` (line sells at value halalas; item scope only).', enum: DiscountType::class, example: 'percentage')]
    #[ResponseField('discount_value', 'integer', 'Basis points for `percentage` (1250 = 12.5%), otherwise halalas.', example: 1500)]
    #[ResponseField('max_discount_amount', 'integer', 'Cap on the discount in halalas; null means uncapped.', nullable: true, example: 5000)]
    #[ResponseField('min_subtotal_amount', 'integer', 'Minimum cart subtotal in halalas; null means no minimum.', nullable: true, example: 10000)]
    #[ResponseField('starts_at', 'string', 'Start (ISO-8601, UTC).', example: '2026-06-01T00:00:00+00:00')]
    #[ResponseField('ends_at', 'string', 'End (ISO-8601, UTC); null means open-ended.', nullable: true, example: '2026-08-31T23:59:59+00:00')]
    #[ResponseField('status', 'string', 'Lifecycle status.', enum: PromotionStatus::class, example: 'active')]
    #[ResponseField('is_live', 'boolean', 'True when active, inside its date range and inside a schedule window right now.', example: true)]
    #[ResponseField('priority', 'integer', 'Higher wins when exclusive promotions compete (-1000 … 1000).', example: 0)]
    #[ResponseField('stacking', 'string', '`exclusive` cannot combine with other promotions; `stackable` can.', enum: PromotionStacking::class, example: 'exclusive')]
    #[ResponseField('funded_by', 'string', 'Who bears the discount.', enum: FundedBy::class, example: 'platform')]
    #[ResponseField('center_share_bps', 'integer', 'Center share of a `shared` discount in basis points; 0 for other funding.', example: 5000)]
    #[ResponseField('total_usage_limit', 'integer', 'Total redemptions allowed; null means unlimited.', nullable: true, example: 1000)]
    #[ResponseField('per_client_usage_limit', 'integer', 'Redemptions allowed per client; null means unlimited.', nullable: true, example: 1)]
    #[ResponseField('usage_count', 'integer', 'Redemptions so far (released ones excluded).', example: 37)]
    #[ResponseField('first_booking_only', 'boolean', 'Only for clients with no previous paid purchase.', example: false)]
    #[ResponseField('targets', 'object[]', 'Targeting rules; with no `include` rule the promotion applies everywhere it can reach. Present when loaded.', required: false)]
    #[ResponseField('targets[].type', 'string', 'Target kind.', enum: TargetType::class, example: 'service')]
    #[ResponseField('targets[].id', 'integer', 'Center or service ID.', example: 31)]
    #[ResponseField('targets[].mode', 'string', 'Include or exclude the target.', enum: TargetMode::class, example: 'include')]
    #[ResponseField('schedules', 'object[]', 'Weekly windows when the promotion is live; empty means the whole date range. Present when loaded.', required: false)]
    #[ResponseField('schedules[].day_of_week', 'integer', 'ISO weekday: 1 = Monday … 7 = Sunday.', example: 5)]
    #[ResponseField('schedules[].start_time', 'string', 'Window start, `HH:MM` (UTC).', example: '16:00')]
    #[ResponseField('schedules[].end_time', 'string', 'Window end, `HH:MM` (UTC). Earlier than start means it wraps past midnight.', example: '23:00')]
    #[ResponseField('cover', 'object', 'Cover image (`MediaResource`); null when there is no cover. Present when loaded.', required: false, nullable: true)]
    #[ResponseField('cover.id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('cover.status', 'string', 'Media lifecycle state.', enum: ['temporary', 'attached'], example: 'attached')]
    #[ResponseField('cover.url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/cover.jpg')]
    #[ResponseField('cover.filename', 'string', 'Stored file name.', example: 'cover.jpg')]
    #[ResponseField('cover.mime_type', 'string', 'MIME type.', example: 'image/jpeg')]
    #[ResponseField('cover.size', 'integer', 'File size in bytes.', example: 184233)]
    #[ResponseField('coupons_count', 'integer', 'Number of coupons. Present on list and show responses.', required: false, example: 2)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-21T11:00:00+00:00')]
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
