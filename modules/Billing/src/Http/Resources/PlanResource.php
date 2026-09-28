<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
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
