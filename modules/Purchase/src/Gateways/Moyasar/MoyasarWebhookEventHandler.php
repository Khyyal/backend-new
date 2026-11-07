<?php

namespace Modules\Purchase\Gateways\Moyasar;

use Modules\Purchase\Contracts\WebhookEventHandler;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\WebhookEvent;

/**
 * Handles Moyasar webhook event types (exact raw string matching).
 *
 * NOTE: Moyasar emits a provider-side typo — the event is "payment_faild"
 * (without an 'e'). We MUST match this exact string; NEVER "correct" it to
 * "payment_failed" or the handler will silently drop real failure events.
 *
 * Mapping:
 *   payment_paid       → Succeeded
 *   payment_faild      → Failed     (TYPO preserved — provider string is incorrect)
 *   payment_refunded   → null       (informational; no auto state change)
 *   payment_voided     → Cancelled
 *   payment_authorized → Processing
 *   payment_captured   → Succeeded
 *   payment_verified   → null       (no auto state change; informational)
 */
class MoyasarWebhookEventHandler implements WebhookEventHandler
{
    public const EVENT_MAP = [
        'payment_paid' => PaymentStatus::Succeeded,
        'payment_faild' => PaymentStatus::Failed,
        'payment_voided' => PaymentStatus::Cancelled,
        'payment_authorized' => PaymentStatus::Processing,
        'payment_captured' => PaymentStatus::Succeeded,
    ];

    public const NO_ACTION_EVENTS = [
        'payment_refunded',
        'payment_verified',
    ];

    public function handle(WebhookEvent $event): ?array
    {
        $eventType = strtolower(trim((string) $event->event_type));
        $payload = (array) ($event->payload ?? []);

        $paymentId = $this->extractPaymentId($payload);
        if ($paymentId === '') {
            return null;
        }

        if (in_array($eventType, self::NO_ACTION_EVENTS, true)) {
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
            'payment_order_id' => $paymentId,
            'payment_status' => $status,
            'payment_id' => $this->extractGivenId($payload),
        ];
    }

    /**
     * Extract the Moyasar payment.id from webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentId(array $payload): string
    {
        // Real webhooks carry the event id at the top and the payment under `data`.
        if (isset($payload['data']) && is_array($payload['data']) && isset($payload['data']['id']) && is_string($payload['data']['id'])) {
            return $payload['data']['id'];
        }

        $candidates = [
            'id',
            'payment_id',
            'paymentId',
            'payment_order_id',
            'order_id',
        ];

        foreach ($candidates as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $this->extractPaymentId($payload['data']);
        }

        if (isset($payload['payment']) && is_array($payload['payment'])) {
            return $this->extractPaymentId($payload['payment']);
        }

        return '';
    }

    /**
     * Our payment uuid, sent to Moyasar as `given_id` when the app created the payment.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractGivenId(array $payload): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $givenId = $data['given_id'] ?? null;

        return is_string($givenId) && $givenId !== '' ? $givenId : null;
    }
}
