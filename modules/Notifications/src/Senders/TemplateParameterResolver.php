<?php

namespace Modules\Notifications\Senders;

use Modules\Centers\Models\User as CenterUser;
use Modules\Clients\Models\Client;
use Modules\Notifications\Enums\RecipientField;
use Modules\Notifications\Enums\TemplateVariableSource;
use Modules\Notifications\Models\NotificationCampaign;

/**
 * Turns a campaign's template parameter bindings into the ordered values for one recipient.
 */
final class TemplateParameterResolver
{
    /**
     * @return list<string>
     */
    public function resolve(NotificationCampaign $campaign, CampaignRecipient $recipient): array
    {
        return array_values(array_map(
            fn (array $binding): string => match (TemplateVariableSource::from($binding['source'])) {
                TemplateVariableSource::Fixed => (string) ($binding['value'] ?? ''),
                TemplateVariableSource::Recipient => $this->field(RecipientField::from((string) $binding['field']), $recipient),
            },
            $campaign->template_parameters ?? [],
        ));
    }

    public function phone(CampaignRecipient $recipient): ?string
    {
        $notifiable = $recipient->notifiable;

        return match (true) {
            $notifiable instanceof Client => $notifiable->phone_number,
            $notifiable instanceof CenterUser => $notifiable->phone,
            default => null,
        };
    }

    private function field(RecipientField $field, CampaignRecipient $recipient): string
    {
        $notifiable = $recipient->notifiable;

        return (string) match ($field) {
            RecipientField::FirstName => $notifiable instanceof Client ? $notifiable->first_name : null,
            RecipientField::LastName => $notifiable instanceof Client ? $notifiable->last_name : null,
            RecipientField::FullName => $notifiable instanceof Client ? $notifiable->full_name : null,
            RecipientField::StaffName => $notifiable instanceof CenterUser ? $notifiable->name : null,
            RecipientField::CenterName => $recipient->center?->name,
            RecipientField::Phone => $this->phone($recipient),
        };
    }
}
