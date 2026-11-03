<?php

namespace Modules\Notifications\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Models\NotificationCampaignDelivery;

/**
 * @mixin NotificationCampaignDelivery
 */
class NotificationCampaignDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,
            'phone' => $this->phone,
            'status' => $this->status,
            'provider_message_id' => $this->provider_message_id,
            'error' => $this->error,
            'created_at' => $this->created_at,
        ];
    }
}
