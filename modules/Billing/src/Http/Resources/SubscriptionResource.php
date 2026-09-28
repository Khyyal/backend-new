<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
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
