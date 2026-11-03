<?php

namespace Modules\Notifications\Senders;

use Illuminate\Support\Str;
use Modules\Notifications\Enums\DeliveryStatus;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Models\NotificationCampaignDelivery;
use Modules\Notifications\Senders\Contracts\CampaignChannelSender;
use Modules\Support\Support\PhoneNumber;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;
use Modules\Support\WhatsApp\WhatsAppSendException;
use Modules\Support\WhatsApp\WhatsAppTemplateMessage;

/**
 * Sends the campaign template to each recipient and records one delivery row per recipient.
 * Recipients that already have a delivery row are skipped, so a retried chunk never sends twice.
 */
final class WhatsAppCampaignSender implements CampaignChannelSender
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly TemplateParameterResolver $parameters,
    ) {}

    /**
     * @throws WhatsAppSendException When the failure affects every message (auth / configuration).
     */
    public function send(NotificationCampaign $campaign, array $recipients): array
    {
        $template = $campaign->template;
        $sent = 0;
        $failed = 0;

        $alreadyDelivered = $campaign->deliveries()
            ->get(['recipient_type', 'recipient_id'])
            ->map(fn (NotificationCampaignDelivery $delivery): string => $delivery->recipient_type.':'.$delivery->recipient_id)
            ->flip();

        foreach ($recipients as $recipient) {
            $notifiable = $recipient->notifiable;
            $key = $notifiable->getMorphClass().':'.$notifiable->getKey();

            if ($alreadyDelivered->has($key)) {
                continue;
            }

            $alreadyDelivered->put($key, true);
            $phone = PhoneNumber::normalize($this->parameters->phone($recipient));

            if ($phone === null) {
                $this->record($campaign, $recipient, null, DeliveryStatus::Failed, error: 'Invalid or missing phone number.');
                $failed++;

                continue;
            }

            try {
                $result = $this->gateway->sendTemplate(new WhatsAppTemplateMessage(
                    toNumber: $phone,
                    templateName: $template->provider_name,
                    language: $template->language,
                    parameters: $this->parameters->resolve($campaign, $recipient),
                ));
            } catch (WhatsAppSendException $e) {
                if ($e->isFatal()) {
                    throw $e;
                }

                $this->record($campaign, $recipient, $phone, DeliveryStatus::Failed, error: Str::limit($e->getMessage().' '.$e->responseBody, 1000));
                $failed++;

                continue;
            }

            $this->record($campaign, $recipient, $phone, DeliveryStatus::Sent, messageId: $result->messageId);
            $sent++;
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private function record(
        NotificationCampaign $campaign,
        CampaignRecipient $recipient,
        ?string $phone,
        DeliveryStatus $status,
        ?string $messageId = null,
        ?string $error = null,
    ): void {
        $campaign->deliveries()->create([
            'recipient_type' => $recipient->notifiable->getMorphClass(),
            'recipient_id' => $recipient->notifiable->getKey(),
            'phone' => $phone,
            'status' => $status,
            'provider_message_id' => $messageId,
            'error' => $error,
        ]);
    }
}
