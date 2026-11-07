<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Purchase\Contracts\VerifiesWebhookPayments;
use Modules\Purchase\Contracts\WebhookEventHandler;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Gateways\FakeWebhookEventHandler;
use Modules\Purchase\Gateways\Moyasar\MoyasarWebhookEventHandler;
use Modules\Purchase\Gateways\Tamara\TamaraWebhookEventHandler;
use Modules\Purchase\Managers\PaymentGatewayManager;
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
            'tamara' => TamaraWebhookEventHandler::class,
            'moyasar' => MoyasarWebhookEventHandler::class,
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
                $this->applyPaymentAction($gateway, $action);
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
    ): WebhookEvent {
        return DB::transaction(function () use (
            $gateway,
            $externalEventId,
            $eventType,
            $payload,
        ): WebhookEvent {
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
     * Finds the payment, asks Tamara-style gateways to authorize it when needed, re-checks a
     * success with the provider and moves the payment to the resulting status.
     *
     * @param  array{payment_order_id: string, payment_status: PaymentStatus, payment_id?: string|null, authorize?: bool}  $action
     */
    private function applyPaymentAction(string $gateway, array $action): void
    {
        $payment = $this->findPayment($gateway, $action);
        $status = $action['payment_status'];
        $driver = app(PaymentGatewayManager::class)->driver($gateway);

        if (($action['authorize'] ?? false) === true && $status !== PaymentStatus::Pending) {
            $status = $driver->authorize($payment);
        } elseif ($status === PaymentStatus::Succeeded && $driver instanceof VerifiesWebhookPayments) {
            $status = $driver->verify($payment);
        }

        $this->paymentStateService->applyStatus($payment, $status);
    }

    /**
     * Provider id first; otherwise our own payment id echoed by the provider, which also links the
     * provider id for later calls. A payment of another gateway is never matched.
     *
     * @param  array{payment_order_id: string, payment_status: PaymentStatus, payment_id?: string|null}  $action
     */
    private function findPayment(string $gateway, array $action): Payment
    {
        $payment = Payment::query()->where('payment_order_id', $action['payment_order_id'])->first();

        if ($payment === null && ($action['payment_id'] ?? null) !== null) {
            $payment = Payment::query()
                ->whereKey($action['payment_id'])
                ->where('payment_company', $gateway)
                ->first();

            if ($payment !== null && ($payment->payment_order_id ?? '') === '') {
                $payment->forceFill(['payment_order_id' => $action['payment_order_id']])->save();
            }
        }

        if ($payment === null) {
            throw new InvalidArgumentException(
                sprintf('No payment found with payment_order_id "%s".', $action['payment_order_id'])
            );
        }

        return $payment;
    }
}
