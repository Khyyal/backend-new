<?php

namespace Modules\Purchase\Gateways;

use Modules\Purchase\Contracts\WebhookEventHandler;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\WebhookEvent;

class FakeWebhookEventHandler implements WebhookEventHandler
{
    public function handle(WebhookEvent $event): ?array
    {
        $payload = $event->payload ?? [];

        if (! isset($payload['payment_order_id']) || ! is_string($payload['payment_order_id'])) {
            return null;
        }

        $statusRaw = $payload['status'] ?? null;
        $status = $statusRaw instanceof PaymentStatus
            ? $statusRaw
            : PaymentStatus::tryFrom((string) $statusRaw);

        if ($status === null) {
            return null;
        }

        return [
            'payment_order_id' => $payload['payment_order_id'],
            'payment_status' => $status,
        ];
    }
}
