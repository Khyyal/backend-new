<?php

namespace Modules\Billing\Http\Resources\Admin;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Centers\Models\Center;

/**
 * A subscription with the center it belongs to (when the subscriber is a center).
 */
class AdminSubscriptionResource extends SubscriptionResource
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
    #[ResponseField('center', 'object', 'The owner center (`id`, `name`, `slug`); null when not loaded or not a center.', nullable: true, example: ['id' => 8, 'name' => 'Al Fursan Stables', 'slug' => 'al-fursan-stables'])]
    public function toArray(Request $request): array
    {
        $subscribable = $this->whenLoaded('subscribable');

        return parent::toArray($request) + [
            'center' => $subscribable instanceof Center ? [
                'id' => $subscribable->id,
                'name' => $subscribable->name,
                'slug' => $subscribable->slug,
            ] : null,
        ];
    }
}
