<?php

namespace Modules\Purchase\Gateways\Moyasar;

use Modules\Purchase\Exceptions\PaymentAuthorizationException;
use Modules\Purchase\Exceptions\PaymentGatewayException;
use Modules\Purchase\Exceptions\PaymentInitializationException;
use Modules\Purchase\Exceptions\PaymentVerificationException;
use Modules\Purchase\Support\Http\PaymentGatewayClientHelpers;
use Throwable;

class MoyasarClient
{
    use PaymentGatewayClientHelpers;

    public const BASE_URL = 'https://api.moyasar.com/v1';

    /**
     * @param  array{enabled?: bool, publishable_key?: string, secret_key?: string, webhook_secret?: string, currency?: string}  $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {
    }

    public function baseUrl(): string
    {
        return self::BASE_URL;
    }

    public function currency(): string
    {
        return (string) ($this->config['currency'] ?? 'SAR');
    }

    public function publishableKey(): string
    {
        return (string) ($this->config['publishable_key'] ?? '');
    }

    public function webhookSecret(): string
    {
        return (string) ($this->config['webhook_secret'] ?? '');
    }

    /**
     * Convert flat SAR decimal amount to integer halalas (multiply by 100, round).
     * This is the ONLY place internal flat-amount touches provider integer halalas.
     */
    public function toHalalas(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * POST /v1/payments — create a Moyasar payment.
     *
     * Used only for authorize (manual=true) flows. Default frontend flow uses
     * publishable key directly (no raw PAN/CVC through backend).
     *
     * Idempotency: given_id = stable Payment.id (never regenerate across retries).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws PaymentInitializationException|PaymentAuthorizationException
     */
    public function createPayment(array $payload): array
    {
        try {
            return $this->withInitializeRetry(function () use ($payload): array {
                $response = $this->baseHttp()
                    ->withBasicAuth($this->secretKey(), '')
                    ->post($this->baseUrl().'/payments', $payload);

                if ($response->failed()) {
                    $response->throw();
                }

                return (array) $response->json();
            });
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->convertRequestException($e, PaymentInitializationException::class);
        }
    }

    /**
     * GET /v1/payments/{id} — fetch payment details.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentVerificationException
     */
    public function fetchPayment(string $paymentId): array
    {
        try {
            $response = $this->baseHttp()
                ->withBasicAuth($this->secretKey(), '')
                ->get($this->baseUrl().'/payments/'.rawurlencode($paymentId));

            if ($response->failed()) {
                $response->throw();
            }

            return (array) $response->json();
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->convertRequestException($e, PaymentVerificationException::class);
        }
    }

    /**
     * POST /v1/payments/{id}/capture — capture an authorized payment.
     *
     * @param  int  $amountHalalas  capture amount in integer halalas
     * @return array<string, mixed>
     */
    public function capturePayment(string $paymentId, int $amountHalalas, string $currency): array
    {
        try {
            $response = $this->baseHttp()
                ->withBasicAuth($this->secretKey(), '')
                ->post($this->baseUrl().'/payments/'.rawurlencode($paymentId).'/capture', [
                    'amount' => $amountHalalas,
                    'currency' => $currency,
                ]);

            if ($response->failed()) {
                $response->throw();
            }

            return (array) $response->json();
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->convertRequestException($e, PaymentGatewayException::class);
        }
    }

    /**
     * POST /v1/payments/{id}/void — void an authorized payment (cancels it).
     *
     * @return array<string, mixed>
     */
    public function voidPayment(string $paymentId): array
    {
        try {
            $response = $this->baseHttp()
                ->withBasicAuth($this->secretKey(), '')
                ->post($this->baseUrl().'/payments/'.rawurlencode($paymentId).'/void');

            if ($response->failed()) {
                $response->throw();
            }

            return (array) $response->json();
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->convertRequestException($e, PaymentGatewayException::class);
        }
    }

    /**
     * POST /v1/payments/{id}/refund — issue (possibly partial) refund.
     *
     * @param  int  $amountHalalas  refund amount in integer halalas
     * @param  string|null  $reason  optional refund reason
     * @return array<string, mixed>
     */
    public function refundPayment(string $paymentId, int $amountHalalas, ?string $reason = null): array
    {
        try {
            $body = ['amount' => $amountHalalas];
            if ($reason !== null && $reason !== '') {
                $body['reason'] = $reason;
            }

            $response = $this->baseHttp()
                ->withBasicAuth($this->secretKey(), '')
                ->post($this->baseUrl().'/payments/'.rawurlencode($paymentId).'/refund', $body);

            if ($response->failed()) {
                $response->throw();
            }

            return (array) $response->json();
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->convertRequestException($e, PaymentGatewayException::class);
        }
    }

    private function secretKey(): string
    {
        return (string) ($this->config['secret_key'] ?? '');
    }
}
