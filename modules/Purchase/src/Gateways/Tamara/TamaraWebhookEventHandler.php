<?php

namespace Modules\Purchase\Gateways\Tamara;

use Modules\Purchase\Contracts\WebhookEventHandler;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\WebhookEvent;

/**
 * Handles Tamara webhook event types:
 *   order_approved  → Processing
 *   order_declined  → Failed
 *   order_authorised → Succeeded
 *   order_canceled  → Cancelled
 *   order_captured  → Succeeded
 *   order_refunded  → null (informational only; no auto state change — refund details in provider_data)
 *   order_expired   → Expired
 */
class TamaraWebhookEventHandler implements WebhookEventHandler
{
    public const EVENT_MAP = [
        'order_approved' => PaymentStatus::Processing,
        'order_declined' => PaymentStatus::Failed,
        'order_authorised' => PaymentStatus::Succeeded,
        'order_canceled' => PaymentStatus::Cancelled,
        'order_cancelled' => PaymentStatus::Cancelled,
        'order_captured' => PaymentStatus::Succeeded,
        'order_expired' => PaymentStatus::Expired,
    ];

    public const REFUND_EVENTS = [
        'order_refunded',
    ];

    public function handle(WebhookEvent $event): ?array
    {
        $eventType = strtolower(trim((string) $event->event_type));
        $payload = (array) ($event->payload ?? []);

        $orderId = $this->extractOrderId($payload);
        if ($orderId === '') {
            return null;
        }

        if (in_array($eventType, self::REFUND_EVENTS, true)) {
            // Refund events are informational. Persistence of refund metadata into
            // Payment.provider_data is handled by a separate post-process listener;
            // here we simply do not trigger a state transition.
            return null;
        }

        if (! isset(self::EVENT_MAP[$eventType])) {
            return null;
        }

        $status = self::EVENT_MAP[$eventType];
        if (! $status instanceof PaymentStatus) {
            return null;
        }

        return [
            'payment_order_id' => $orderId,
            'payment_status' => $status,
        ];
    }

    /**
     * Extract the Tamara order ID from webhook payload (order_id key).
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractOrderId(array $payload): string
    {
        $candidates = [
            'order_id',
            'orderId',
            'order_reference_id',
            'payment_order_id',
            'id',
        ];

        foreach ($candidates as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $this->extractOrderId($payload['data']);
        }

        return '';
    }
}
