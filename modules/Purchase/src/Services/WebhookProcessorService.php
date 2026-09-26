<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Purchase\Contracts\WebhookEventHandler;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Gateways\FakeWebhookEventHandler;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\WebhookEvent;

class WebhookProcessorService
{
    /**
     * @var array<string, class-string<WebhookEventHandler>>
     */
    private array $handlers;

    /**
     * @param  array<string, class-string<WebhookEventHandler>>  $handlers  gateway => handler class-string map
     */
    public function __construct(
        private readonly PaymentStateService $paymentStateService,
        array $handlers = [
            'fake' => FakeWebhookEventHandler::class,
        ],
    ) {
        $this->handlers = $handlers;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(
        string $gateway,
        string $externalEventId,
        string $eventType,
        array $payload,
    ): void {
        if ($gateway === '') {
            throw new InvalidArgumentException('Gateway name cannot be empty.');
        }

        if ($externalEventId === '') {
            throw new InvalidArgumentException('External event id cannot be empty.');
        }

        // First, upsert the WebhookEvent attempt record in an isolated transaction so
        // the failure audit trail persists even if downstream processing throws.
        $event = $this->recordAttempt($gateway, $externalEventId, $eventType, $payload);

        // Idempotency: no-op if already processed successfully.
        if ($event->processed_at !== null) {
            return;
        }

        try {
            $handlerClass = $this->handlers[$gateway] ?? null;
            if ($handlerClass === null) {
                throw new InvalidArgumentException(sprintf('No webhook handler registered for gateway "%s".', $gateway));
            }

            $handler = app($handlerClass);
            if (! $handler instanceof WebhookEventHandler) {
                throw new InvalidArgumentException(
                    sprintf('Handler "%s" must implement %s.', $handlerClass, WebhookEventHandler::class)
                );
            }

            $action = $handler->handle($event);

            if ($action !== null) {
                $this->applyPaymentAction($action);
            }

            // Mark processed (separate update that commits immediately)
            $event->processed_at = now();
            $event->failed_at = null;
            $event->save();
        } catch (\Throwable $e) {
            // Persist failure state so retries increment attempts and failed_at is visible
            $event->failed_at = now();
            $event->save();

            throw $e;
        }
    }

    /**
     * Atomically findOrCreate the WebhookEvent and increment attempts counter.
     * Uses its own DB transaction to ensure audit trail always persists.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordAttempt(
        string $gateway,
        string $externalEventId,
        string $eventType,
        array $payload,
    ): \Modules\Purchase\Models\WebhookEvent {
        return DB::transaction(function () use (
            $gateway,
            $externalEventId,
            $eventType,
            $payload,
        ): \Modules\Purchase\Models\WebhookEvent {
            $event = WebhookEvent::query()
                ->where('gateway', $gateway)
                ->where('external_event_id', $externalEventId)
                ->lockForUpdate()
                ->first();

            if ($event === null) {
                $event = WebhookEvent::create([
                    'gateway' => $gateway,
                    'external_event_id' => $externalEventId,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'received_at' => now(),
                    'processed_at' => null,
                    'failed_at' => null,
                    'attempts' => 0,
                ]);

                $event = WebhookEvent::query()
                    ->whereKey($event->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            // Idempotency: if already processed successfully, do not increment attempts.
            // Caller will short-circuit on processed_at.
            if ($event->processed_at === null) {
                $event->attempts = ($event->attempts ?? 0) + 1;
                $event->save();
            }

            return $event;
        });
    }

    /**
     * @param  array{payment_order_id: string, payment_status: PaymentStatus}  $action
     */
    private function applyPaymentAction(array $action): void
    {
        $orderId = $action['payment_order_id'];
        $status = $action['payment_status'];

        $payment = Payment::query()
            ->where('payment_order_id', $orderId)
            ->first();

        if ($payment === null) {
            throw new InvalidArgumentException(
                sprintf('No payment found with payment_order_id "%s".', $orderId)
            );
        }

        match ($status) {
            PaymentStatus::Processing => $this->paymentStateService->markProcessing($payment),
            PaymentStatus::Succeeded => $this->paymentStateService->markSucceeded($payment),
            PaymentStatus::Failed => $this->paymentStateService->markFailed($payment),
            PaymentStatus::Cancelled => $this->paymentStateService->cancel($payment),
            PaymentStatus::Expired => $this->paymentStateService->expire($payment),
            PaymentStatus::Pending => null, // no state change: pending-to-pending is a no-op
        };
    }
}
