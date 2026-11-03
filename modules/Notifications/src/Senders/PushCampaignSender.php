<?php

namespace Modules\Notifications\Senders;

use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Notifications\CampaignNotification;
use Modules\Notifications\Senders\Contracts\CampaignChannelSender;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

final class PushCampaignSender implements CampaignChannelSender
{
    public function __construct(
        private readonly NotificationServiceContract $notifications,
    ) {}

    public function send(NotificationCampaign $campaign, array $recipients): array
    {
        $notifiables = collect($recipients)
            ->map(fn (CampaignRecipient $recipient) => $recipient->notifiable)
            ->unique(fn ($notifiable): string => $notifiable->getMorphClass().':'.$notifiable->getKey())
            ->values();

        if ($notifiables->isNotEmpty()) {
            $this->notifications->send($notifiables, new CampaignNotification($campaign));
        }

        return ['sent' => $notifiables->count(), 'failed' => 0];
    }
}
