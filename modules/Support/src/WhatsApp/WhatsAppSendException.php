<?php

namespace Modules\Support\WhatsApp;

use RuntimeException;
use Throwable;

class WhatsAppSendException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    /**
     * Authentication or configuration failures affect every message, not just one recipient.
     */
    public function isFatal(): bool
    {
        return in_array($this->status, [401, 403], true) || $this->status === null;
    }
}
