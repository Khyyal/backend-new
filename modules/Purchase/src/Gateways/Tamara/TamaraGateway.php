<?php

namespace Modules\Purchase\Gateways\Tamara;

use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

class TamaraGateway implements PaymentGateway
{
    public function __construct(
        private readonly TamaraClient $client,
        private readonly TamaraStatusMapper $mapper = new TamaraStatusMapper(),
    ) {
    }

    /**
     * Create a Tamara checkout. Idempotency: order_reference_id = stable Payment->id
     * so retries of the same Payment row do not create duplicate Tamara orders.
     *
     * @return array{checkout_url: string, provider_reference: string, checkout_id: string, tamara_status: string}
     */
    public function initialize(Purchase $purchase, Payment $payment): array
    {
        $currency = $this->client->currency();
        $items = $this->buildItems($purchase, $currency);

        $merchantUrls = $this->client->merchantUrls();

        $payload = [
            'order_reference_id' => (string) $payment->getKey(),
            'order_number' => (string) $purchase->getKey(),
            'total_amount' => [
                'amount' => (float) $payment->amount,
                'currency' => $currency,
            ],
            'tax_amount' => [
                'amount' => (float) ($purchase->tax_amount ?? 0),
                'currency' => $currency,
            ],
            'shipping_amount' => [
                'amount' => 0,
                'currency' => $currency,
            ],
            'discount_amount' => [
                'amount' => (float) ($purchase->discount_amount ?? 0),
                'currency' => $currency,
            ],
            'items' => $items,
            'merchant_url' => [
                'success' => $merchantUrls['success'] ?? '',
                'failure' => $merchantUrls['failure'] ?? '',
                'cancel' => $merchantUrls['cancel'] ?? '',
                'notification' => $merchantUrls['notification'] ?? '',
            ],
            'locale' => $this->client->locale(),
            'country' => $this->client->country(),
            'currency' => $currency,
            'payment_type' => $this->client->defaultPaymentType(),
            'description' => 'Purchase #'.$purchase->getKey(),
        ];

        $response = $this->client->createCheckout($payload);

        $orderId = (string) ($response['order_id'] ?? '');
        $checkoutId = (string) ($response['checkout_id'] ?? '');
        $checkoutUrl = (string) ($response['checkout_url'] ?? '');
        $rawStatus = (string) ($response['status'] ?? 'new');

        return [
            'checkout_url' => $checkoutUrl,
            'provider_reference' => $orderId,
            'checkout_id' => $checkoutId,
            'tamara_status' => $rawStatus,
        ];
    }

    /**
     * Sync fetches Tamara order via GET /orders/{order_id}.
     * Returns internal PaymentStatus; callers apply transition to Payment row.
     * Raw provider status persisted inside provider_data.tamara_status.
     */
    public function sync(Payment $payment): PaymentStatus
    {
        $orderId = (string) ($payment->payment_order_id ?? '');
        if ($orderId === '') {
            return PaymentStatus::Pending;
        }

        $response = $this->client->getOrder($orderId);
        $rawStatus = strtolower((string) ($response['status'] ?? ''));

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['tamara_status'] = $rawStatus;
        $providerData['tamara_order'] = $response;
        $payment->provider_data = $providerData;
        $payment->save();

        $mapped = $this->mapper->map($rawStatus);
        if ($mapped === null) {
            return PaymentStatus::Pending;
        }

        return $mapped;
    }

    /**
     * Verify for Tamara is a server-side sync. Frontend success/failure callbacks
     * MUST NEVER directly mark payment Succeeded — they call verify() which syncs.
     */
    public function verify(Payment $payment): PaymentStatus
    {
        return $this->sync($payment);
    }

    /**
     * POST /orders/{orderId}/authorise — British spelling endpoint.
     */
    public function authorize(Payment $payment): PaymentStatus
    {
        $orderId = (string) ($payment->payment_order_id ?? '');
        if ($orderId === '') {
            return PaymentStatus::Pending;
        }

        $response = $this->client->authorizeOrder($orderId);
        $rawStatus = strtolower((string) ($response['status'] ?? 'authorised'));

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['tamara_status'] = $rawStatus;
        $providerData['tamara_authorize_response'] = $response;
        $payment->provider_data = $providerData;
        $payment->save();

        return $this->mapper->mapOrFallback($rawStatus, PaymentStatus::Processing);
    }

    public function capture(Payment $payment): PaymentStatus
    {
        $orderId = (string) ($payment->payment_order_id ?? '');
        if ($orderId === '') {
            return PaymentStatus::Pending;
        }

        $payload = [
            'order_id' => $orderId,
            'total_amount' => [
                'amount' => (float) $payment->amount,
                'currency' => $this->client->currency(),
            ],
        ];

        $response = $this->client->capturePayment($payload);
        $rawStatus = strtolower((string) ($response['status'] ?? 'fully_captured'));

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['tamara_status'] = $rawStatus;
        $providerData['tamara_capture_response'] = $response;
        $payment->provider_data = $providerData;
        $payment->save();

        return $this->mapper->mapOrFallback($rawStatus, PaymentStatus::Succeeded);
    }

    public function cancel(Payment $payment): PaymentStatus
    {
        $orderId = (string) ($payment->payment_order_id ?? '');
        if ($orderId === '') {
            return PaymentStatus::Cancelled;
        }

        $response = $this->client->cancelOrder($orderId);
        $rawStatus = strtolower((string) ($response['status'] ?? 'canceled'));

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['tamara_status'] = $rawStatus;
        $providerData['tamara_cancel_response'] = $response;
        $payment->provider_data = $providerData;
        $payment->save();

        return $this->mapper->mapOrFallback($rawStatus, PaymentStatus::Cancelled);
    }

    public function refund(Payment $payment, $amount): bool
    {
        $orderId = (string) ($payment->payment_order_id ?? '');
        if ($orderId === '') {
            return false;
        }

        $payload = [
            'order_id' => $orderId,
            'total_amount' => [
                'amount' => (float) $amount,
                'currency' => $this->client->currency(),
            ],
        ];

        $response = $this->client->refundPayment($orderId, $payload);

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['tamara_refunded'] = true;
        $providerData['tamara_refund_amount'] = (float) $amount;
        $providerData['tamara_refund_response'] = $response;
        $providerData['tamara_status'] = $this->mapper::REFUNDED;
        $payment->provider_data = $providerData;
        $payment->save();

        return true;
    }

    /**
     * Build Tamara items[] array from Purchase items. Always at least one synthetic item
     * so payload passes provider validation even if items relationship is empty.
     *
     * @return list<array<string, mixed>>
     */
    private function buildItems(Purchase $purchase, string $currency): array
    {
        $items = [];
        foreach ($purchase->items as $item) {
            $qty = (int) ($item->quantity ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }

            $items[] = [
                'reference_id' => (string) ($item->getKey() ?? ('line-'.count($items))),
                'name' => (string) ($item->name ?? 'Item'),
                'quantity' => $qty,
                'unit_price' => [
                    'amount' => (float) ($item->unit_price ?? 0),
                    'currency' => $currency,
                ],
                'tax_amount' => [
                    'amount' => (float) ($item->tax_amount ?? 0),
                    'currency' => $currency,
                ],
                'discount_amount' => [
                    'amount' => (float) ($item->discount_amount ?? 0),
                    'currency' => $currency,
                ],
                'total_amount' => [
                    'amount' => (float) ($item->total_amount ?? 0),
                    'currency' => $currency,
                ],
            ];
        }

        if (count($items) === 0) {
            $items[] = [
                'reference_id' => 'purchase-'.$purchase->getKey(),
                'name' => 'Purchase #'.$purchase->getKey(),
                'quantity' => 1,
                'unit_price' => [
                    'amount' => (float) ($purchase->total_amount ?? 0),
                    'currency' => $currency,
                ],
                'tax_amount' => ['amount' => 0, 'currency' => $currency],
                'discount_amount' => ['amount' => 0, 'currency' => $currency],
                'total_amount' => [
                    'amount' => (float) ($purchase->total_amount ?? 0),
                    'currency' => $currency,
                ],
            ];
        }

        return $items;
    }
}
