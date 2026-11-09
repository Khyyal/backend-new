<?php

namespace Modules\Notifications\Senders;

use Illuminate\Support\Facades\Log;
use Modules\Centers\Models\Center;
use Modules\Support\Support\PhoneNumber;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;
use Modules\Support\WhatsApp\WhatsAppSendException;
use Modules\Support\WhatsApp\WhatsAppTemplateMessage;

/**
 * Sends a transactional WhatsApp template to a center's primary user.
 * Nothing is sent while Voxa is not configured.
 */
final class CenterWhatsAppNotifier
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
    ) {}

    /**
     * @param  list<string>  $parameters  Body text values, in template order.
     *
     * @throws WhatsAppSendException When the failure affects every message (auth / configuration), so the job retries.
     */
    public function send(Center $center, string $templateName, array $parameters): bool
    {
        if (blank(config('services.voxa.client_id')) || blank(config('services.voxa.channel_id'))) {
            return false;
        }

        $phone = PhoneNumber::normalize($center->primaryUser?->phone);

        if ($phone === null) {
            Log::warning('Center WhatsApp skipped: primary user has no valid phone.', ['center_id' => $center->getKey(), 'template' => $templateName]);

            return false;
        }

        try {
            $this->gateway->sendTemplate(new WhatsAppTemplateMessage(
                toNumber: $phone,
                templateName: $templateName,
                language: (string) config('notifications.whatsapp.language'),
                parameters: $parameters,
            ));
        } catch (WhatsAppSendException $e) {
            if ($e->isFatal()) {
                throw $e;
            }

            Log::error('Center WhatsApp failed.', [
                'center_id' => $center->getKey(),
                'template' => $templateName,
                'status' => $e->status,
                'response' => $e->responseBody,
            ]);

            return false;
        }

        return true;
    }
}
