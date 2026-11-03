<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Notifications\Enums\DeliveryStatus;

/**
 * One WhatsApp message sent (or attempted) to one recipient of a campaign.
 */
class NotificationCampaignDelivery extends Model
{
    protected $fillable = [
        'notification_campaign_id',
        'recipient_type',
        'recipient_id',
        'phone',
        'status',
        'provider_message_id',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'notification_campaign_id');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
