<?php

namespace Modules\Support\WhatsApp;

final readonly class WhatsAppSendResult
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public ?string $messageId,
        public array $response = [],
    ) {}
}
