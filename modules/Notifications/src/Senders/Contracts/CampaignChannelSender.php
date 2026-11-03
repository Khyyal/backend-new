<?php

namespace Modules\Notifications\Senders\Contracts;

use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Senders\CampaignRecipient;

interface CampaignChannelSender
{
    /**
     * Send the campaign to one chunk of recipients.
     *
     * @param  list<CampaignRecipient>  $recipients
     * @return array{sent: int, failed: int}
     */
    public function send(NotificationCampaign $campaign, array $recipients): array;
}
