<?php

namespace Modules\Purchase\Gateways\Moyasar;

use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Exceptions\PaymentVerificationException;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;

class MoyasarGateway implements PaymentGateway
{
    public function __construct(
        private readonly MoyasarClient $client,
        private readonly MoyasarStatusMapper $mapper = new MoyasarStatusMapper(),
        private readonly MoyasarSourceNormalizer $normalizer = new MoyasarSourceNormalizer(),
    ) {
    }

    /**
     * Moyasar initialize does NOT call the backend createPayment endpoint
     * (Moyasar is frontend-first via publishable key; no PAN/CVC through our backend).
     *
     * Returns a frontend-only configuration payload with:
     *   - type: 'moyasar' (marker for frontend SDK selector)
     *   - publishable_key: for client-side tokenization only (never secret key)
     *   - amount_halalas: integer halalas of Payment.amount
     *   - currency: e.g. "SAR"
     *   - given_id: stable Payment.id (used as idempotency key on frontend create)
     *   - callback_url, description
     *
     * @return array{type: string, publishable_key: string, amount_halalas: int, currency: string, given_id: string, description: string}
     */
    public function initialize(Purchase $purchase, Payment $payment): array
    {
        return [
            'type' => 'moyasar',
            'publishable_key' => $this->client->publishableKey(),
            'amount_halalas' => $this->client->toHalalas((float) $payment->amount),
            'currency' => $this->client->currency(),
            'given_id' => (string) $payment->getKey(),
            'description' => 'Purchase #'.$purchase->getKey(),
        ];
    }

    /**
     * Sync GET /v1/payments/{payment_order_id}.
     * Persists raw source type / company / transaction_url in provider_data.
     * Returns internal PaymentStatus for callers to apply.
     */
    public function sync(Payment $payment): PaymentStatus
    {
        $paymentId = (string) ($payment->payment_order_id ?? '');
        if ($paymentId === '') {
            return PaymentStatus::Pending;
        }

        $response = $this->client->fetchPayment($paymentId);
        $rawStatus = strtolower((string) ($response['status'] ?? ''));

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['moyasar_status'] = $rawStatus;
        $providerData['moyasar_payment'] = $response;

        if (isset($response['source']) && is_array($response['source'])) {
            $norm = $this->normalizer->fromSource($response['source']);
            $providerData['source_type'] = $norm['raw_source_type'];
            $providerData['source_company'] = $norm['source_company'];
            $providerData['payment_type_normalized'] = $norm['payment_type'];

            if (($payment->payment_type ?? '') === '' || $payment->payment_type === 'online') {
                $payment->payment_type = $norm['payment_type'];
            }
        }

        if (isset($response['transaction_url'])) {
            $providerData['transaction_url'] = (string) $response['transaction_url'];
        }

        if (isset($response['amount'])) {
            $providerData['provider_amount_halalas'] = (int) $response['amount'];
        }

        $payment->provider_data = $providerData;
        $payment->save();

        $mapped = $this->mapper->map($rawStatus);
        if ($mapped === null) {
            return PaymentStatus::Pending;
        }

        return $mapped;
    }

    /**
     * Verify = sync + amount integrity check.
     * Browser-success callbacks MUST call verify (never trust frontend status).
     * Amount mismatch between our Payment.amount and the provider fetch throws VerificationException.
     *
     * @throws PaymentVerificationException
     */
    public function verify(Payment $payment): PaymentStatus
    {
        $status = $this->sync($payment);

        $providerData = (array) ($payment->provider_data ?? []);
        $providerHalalas = (int) ($providerData['provider_amount_halalas'] ?? -1);
        $expectedHalalas = $this->client->toHalalas((float) $payment->amount);

        if ($providerHalalas !== -1 && $providerHalalas !== $expectedHalalas) {
            throw new PaymentVerificationException(sprintf(
                'Amount mismatch detected. Expected %d halalas, provider reported %d halalas.',
                $expectedHalalas,
                $providerHalalas,
            ));
        }

        return $status;
    }

    /**
     * Authorize = create a manual=true backend payment.
     * Only used for server-initiated authorize flows (NOT the default card flow).
     */
    public function authorize(Payment $payment): PaymentStatus
    {
        $currency = $this->client->currency();
        $payload = [
            'amount' => $this->client->toHalalas((float) $payment->amount),
            'currency' => $currency,
            'given_id' => (string) $payment->getKey(),
            'description' => 'Authorize Payment #'.$payment->getKey(),
            'manual' => true,
            'source' => [
                'type' => $payment->payment_type ?: 'creditcard',
            ],
        ];

        $response = $this->client->createPayment($payload);
        $rawStatus = strtolower((string) ($response['status'] ?? 'authorized'));
        $paymentId = (string) ($response['id'] ?? '');

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['moyasar_status'] = $rawStatus;
        $providerData['moyasar_authorize_response'] = $response;
        if (isset($response['source']) && is_array($response['source'])) {
            $norm = $this->normalizer->fromSource($response['source']);
            $providerData['source_type'] = $norm['raw_source_type'];
            $providerData['source_company'] = $norm['source_company'];
        }

        $payment->provider_data = $providerData;
        if (($payment->payment_order_id ?? '') === '' && $paymentId !== '') {
            $payment->payment_order_id = $paymentId;
        }
        $payment->save();

        return $this->mapper->mapOrFallback($rawStatus, PaymentStatus::Processing);
    }

    public function capture(Payment $payment): PaymentStatus
    {
        $paymentId = (string) ($payment->payment_order_id ?? '');
        if ($paymentId === '') {
            return PaymentStatus::Pending;
        }

        $halalas = $this->client->toHalalas((float) $payment->amount);
        $currency = $this->client->currency();

        $response = $this->client->capturePayment($paymentId, $halalas, $currency);
        $rawStatus = strtolower((string) ($response['status'] ?? 'captured'));

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['moyasar_status'] = $rawStatus;
        $providerData['moyasar_capture_response'] = $response;
        $payment->provider_data = $providerData;
        $payment->save();

        return $this->mapper->mapOrFallback($rawStatus, PaymentStatus::Succeeded);
    }

    public function cancel(Payment $payment): PaymentStatus
    {
        $paymentId = (string) ($payment->payment_order_id ?? '');
        if ($paymentId === '') {
            return PaymentStatus::Cancelled;
        }

        $response = $this->client->voidPayment($paymentId);
        $rawStatus = strtolower((string) ($response['status'] ?? 'voided'));

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['moyasar_status'] = $rawStatus;
        $providerData['moyasar_void_response'] = $response;
        $payment->provider_data = $providerData;
        $payment->save();

        return $this->mapper->mapOrFallback($rawStatus, PaymentStatus::Cancelled);
    }

    public function refund(Payment $payment, $amount): bool
    {
        $paymentId = (string) ($payment->payment_order_id ?? '');
        if ($paymentId === '') {
            return false;
        }

        $halalas = $this->client->toHalalas((float) $amount);

        $response = $this->client->refundPayment($paymentId, $halalas);

        $providerData = (array) ($payment->provider_data ?? []);
        $providerData['moyasar_refunded'] = true;
        $providerData['moyasar_refund_amount_halalas'] = $halalas;
        $providerData['moyasar_refund_amount'] = (float) $amount;
        $providerData['moyasar_refund_response'] = $response;
        $providerData['moyasar_status'] = 'refunded';
        $payment->provider_data = $providerData;
        $payment->save();

        return true;
    }
}
