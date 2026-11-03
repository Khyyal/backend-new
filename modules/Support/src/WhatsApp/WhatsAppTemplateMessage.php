<?php

namespace Modules\Support\WhatsApp;

final readonly class WhatsAppTemplateMessage
{
    /**
     * @param  string  $toNumber  Digits only, international format (e.g. 9665XXXXXXXX).
     * @param  list<string>  $parameters  Values for the template placeholders, in order.
     */
    public function __construct(
        public string $toNumber,
        public string $templateName,
        public string $language,
        public array $parameters = [],
    ) {}
}
