<?php

namespace Modules\Promotion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'center_id' => $this->center_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type?->value,
            'value' => $this->value,
            'scope' => $this->scope?->value,
            'application_method' => $this->application_method?->value,
            'minimum_amount' => $this->minimum_amount,
            'maximum_discount' => $this->maximum_discount,
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_customer' => $this->usage_limit_per_customer,
            'is_stackable' => $this->is_stackable,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'status' => $this->status?->value,
            'coupons_count' => $this->whenCounted('coupons'),
            'redemptions_count' => $this->whenCounted('redemptions'),
            'services_count' => $this->whenCounted('services'),
            'service_ids' => $this->whenLoaded('services', fn () => $this->services->modelKeys()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
