<?php

namespace Modules\Support\WhatsApp\Contracts;

use Modules\Support\WhatsApp\WhatsAppSendException;
use Modules\Support\WhatsApp\WhatsAppSendResult;
use Modules\Support\WhatsApp\WhatsAppTemplateMessage;

interface WhatsAppGateway
{
    /**
     * Send a pre-approved template; required for business-initiated messages.
     *
     * @throws WhatsAppSendException
     */
    public function sendTemplate(WhatsAppTemplateMessage $message): WhatsAppSendResult;

    /**
     * Send free text; only delivered inside an open 24h customer session.
     *
     * @throws WhatsAppSendException
     */
    public function sendText(string $toNumber, string $text): WhatsAppSendResult;
}
