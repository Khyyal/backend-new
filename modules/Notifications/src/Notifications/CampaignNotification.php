<?php

namespace Modules\Notifications\Notifications;

use Modules\Notifications\Models\NotificationCampaign;
use Modules\Support\Notifications\AppNotification;

/**
 * Push campaign: the copy comes from the campaign's own translations instead of lang files.
 */
final class CampaignNotification extends AppNotification
{
    public function __construct(
        public readonly NotificationCampaign $campaign,
    ) {}

    public function type(): string
    {
        return 'campaign';
    }

    protected function translationKey(): string
    {
        return '';
    }

    public function data(): array
    {
        return [
            'campaign_id' => $this->campaign->getKey(),
            'action_type' => $this->campaign->action_type->value,
            'action_target_id' => $this->campaign->action_target_id,
            'action_url' => $this->campaign->action_url,
        ];
    }

    public function title(string $locale): string
    {
        return (string) $this->campaign->getTranslation('title', $locale);
    }

    public function body(string $locale): string
    {
        return (string) $this->campaign->getTranslation('body', $locale);
    }
}
