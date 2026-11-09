<?php

namespace Modules\Notifications\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Notifications\Enums\DeliveryStatus;
use Modules\Notifications\Models\NotificationCampaignDelivery;

/**
 * @mixin NotificationCampaignDelivery
 */
class NotificationCampaignDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Delivery ID.', example: 9001)]
    #[ResponseField('recipient_type', 'string', 'Recipient morph type (client or center user).', example: 'client')]
    #[ResponseField('recipient_id', 'integer', 'Recipient ID.', example: 77)]
    #[ResponseField('phone', 'string', 'Phone the message was sent to.', example: '+966500000003')]
    #[ResponseField('status', 'string', 'Delivery result.', enum: DeliveryStatus::class, example: 'sent')]
    #[ResponseField('provider_message_id', 'string', 'Voxa message ID.', nullable: true, example: 'wamid.HBgMOTY2NTAwMDAwMDAzFQIAERgSN0')]
    #[ResponseField('error', 'string', 'Provider error when failed.', nullable: true, example: 'No-example')]
    #[ResponseField('created_at', 'string', 'Send time (ISO-8601).', example: '2026-06-01T08:00:00.000000Z')]
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
