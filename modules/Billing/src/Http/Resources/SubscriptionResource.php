<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Billing\Enums\SubscriptionStatus;

class SubscriptionResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Subscription ID.', example: 21)]
    #[ResponseField('subscribable_type', 'string', 'Morph type of the owner (a center).', example: 'center')]
    #[ResponseField('subscribable_id', 'integer', 'Owner center ID.', example: 8)]
    #[ResponseField('plan_id', 'integer', 'Plan ID.', example: 1)]
    #[ResponseField('status', 'string', '`pending` until paid, `trialing`, `active`, then `canceled` or `expired`.', enum: SubscriptionStatus::class, example: 'active')]
    #[ResponseField('trial_ends_at', 'string', 'Trial end; null without a trial (ISO-8601, UTC).', nullable: true, example: 'No-example')]
    #[ResponseField('starts_at', 'string', 'Start (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    #[ResponseField('ends_at', 'string', 'End of the current interval (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    #[ResponseField('canceled_at', 'string', 'Cancellation time (ISO-8601, UTC).', nullable: true, example: 'No-example')]
    #[ResponseField('plan', 'object', 'The plan (`PlanResource`). Present when loaded.', required: false, example: ['id' => 1, 'name' => ['en' => 'Pro', 'ar' => 'احترافي'], 'slug' => 'pro', 'price' => 49900, 'billing_interval' => 'monthly', 'status' => 'active', 'trial_days' => 14])]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    #[ResponseField('updated_at', 'string', 'Last update (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscribable_type' => $this->subscribable_type,
            'subscribable_id' => $this->subscribable_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status?->value,
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'canceled_at' => $this->canceled_at?->toISOString(),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
