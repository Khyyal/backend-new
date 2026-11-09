<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

class PlanResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Plan ID.', example: 1)]
    #[ResponseField('name', 'object', 'Name keyed by locale (`en` required, `ar` optional).', example: ['en' => 'Pro', 'ar' => 'احترافي'])]
    #[ResponseField('slug', 'string', 'Unique slug.', example: 'pro')]
    #[ResponseField('description', 'object', 'Description keyed by locale.', nullable: true, example: ['en' => 'For growing centers.', 'ar' => 'للمراكز النامية.'])]
    #[ResponseField('price', 'integer', 'Price per interval in halalas (0 = free).', example: 49900)]
    #[ResponseField('billing_interval', 'string', 'Billing period.', enum: BillingInterval::class, example: 'monthly')]
    #[ResponseField('display_features', 'array', 'Free-form marketing bullet points shown on the plan card.', nullable: true, example: ['Unlimited bookings', 'Custom website'])]
    #[ResponseField('status', 'string', 'Only `active` plans can be subscribed to.', enum: PlanStatus::class, example: 'active')]
    #[ResponseField('trial_days', 'integer', 'Free trial days (0-365).', example: 14)]
    #[ResponseField('features', 'object[]', 'Attached features (`FeatureResource` with `enabled`). Present when loaded.', required: false, example: [['id' => 3, 'key' => 'custom_site', 'name' => 'Custom website', 'is_quota' => false, 'enabled' => true]])]
    #[ResponseField('limits', 'object[]', 'Attached limits (`LimitResource` with `value`). Present when loaded.', required: false, example: [['id' => 2, 'key' => 'max_services', 'name' => 'Maximum services', 'value' => 20]])]
    #[ResponseField('features_count', 'integer', 'Attached features.', required: false, example: 4)]
    #[ResponseField('limits_count', 'integer', 'Attached limits.', required: false, example: 2)]
    #[ResponseField('subscriptions_count', 'integer', 'Subscriptions to the plan.', required: false, example: 12)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    #[ResponseField('updated_at', 'string', 'Last update (ISO-8601, UTC).', example: '2026-06-01T00:00:00.000000Z')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'billing_interval' => $this->billing_interval?->value,
            'display_features' => $this->display_features,
            'status' => $this->status?->value,
            'trial_days' => $this->trial_days,
            'features' => FeatureResource::collection($this->whenLoaded('features')),
            'limits' => LimitResource::collection($this->whenLoaded('limits')),
            'features_count' => $this->whenCounted('features'),
            'limits_count' => $this->whenCounted('limits'),
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
