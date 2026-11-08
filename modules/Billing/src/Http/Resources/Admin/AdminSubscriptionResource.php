<?php

namespace Modules\Billing\Http\Resources\Admin;

use Illuminate\Http\Request;
use Modules\Billing\Http\Resources\SubscriptionResource;
use Modules\Centers\Models\Center;

/**
 * A subscription with the center it belongs to (when the subscriber is a center).
 */
class AdminSubscriptionResource extends SubscriptionResource
{
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
