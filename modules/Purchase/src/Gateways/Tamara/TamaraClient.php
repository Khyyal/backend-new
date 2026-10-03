<?php

namespace Modules\Purchase\Gateways\Tamara;

use Modules\Purchase\Exceptions\PaymentAuthorizationException;
use Modules\Purchase\Exceptions\PaymentGatewayException;
use Modules\Purchase\Exceptions\PaymentInitializationException;
use Modules\Purchase\Exceptions\PaymentVerificationException;
use Modules\Purchase\Support\Http\PaymentGatewayClientHelpers;
use Throwable;

class TamaraClient
{
    use PaymentGatewayClientHelpers;

    public const SANDBOX_URL = 'https://api-sandbox.tamara.co';

    public const PRODUCTION_URL = 'https://api.tamara.co';

    /**
     * @param  array{enabled?: bool, environment?: string, api_token?: string, notification_token?: string, country?: string, currency?: string, locale?: string, urls?: array{success?: string, failure?: string, cancel?: string, notification?: string}, default_payment_type?: string}  $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {
    }

    /**
     * Resolve the base URL based on environment (sandbox vs production).
     */
    public function baseUrl(): string
    {
        $env = strtolower((string) ($this->config['environment'] ?? 'sandbox'));

        return $env === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL;
    }

    /**
     * POST /checkout — create a Tamara checkout session.
     * Idempotency: order_reference_id = stable Payment.id (passed in payload).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>  decoded JSON response: {order_id, checkout_url, checkout_id, ...}
     *
     * @throws PaymentInitializationException
     */
    public function createCheckout(array $payload): array
    {
        try {
            return $this->withInitializeRetry(function () use ($payload): array {
                $response = $this->baseHttp()
                    ->withToken($this->apiToken())
                    ->post($this->baseUrl().'/checkout', $payload);

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
     * GET /orders/{orderId} — fetch current Tamara order state.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentVerificationException
     */
    public function getOrder(string $orderId): array
    {
        try {
            $response = $this->baseHttp()
                ->withToken($this->apiToken())
                ->get($this->baseUrl().'/orders/'.rawurlencode($orderId));

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
     * POST /orders/{orderId}/authorise — British spelling per Tamara docs.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentAuthorizationException
     */
    public function authorizeOrder(string $orderId): array
    {
        try {
            $response = $this->baseHttp()
                ->withToken($this->apiToken())
                ->post($this->baseUrl().'/orders/'.rawurlencode($orderId).'/authorise');

            if ($response->failed()) {
                $response->throw();
            }

            return (array) $response->json();
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->convertRequestException($e, PaymentAuthorizationException::class);
        }
    }

    /**
     * POST /payments/capture — capture an authorised order.
     *
     * @param  array<string, mixed>  $payload  {order_id, total_amount: {amount, currency}, ...}
     * @return array<string, mixed>
     */
    public function capturePayment(array $payload): array
    {
        try {
            $response = $this->baseHttp()
                ->withToken($this->apiToken())
                ->post($this->baseUrl().'/payments/capture', $payload);

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
     * POST /orders/{orderId}/cancel — cancel an existing (non-captured) order.
     *
     * @return array<string, mixed>
     */
    public function cancelOrder(string $orderId): array
    {
        try {
            $response = $this->baseHttp()
                ->withToken($this->apiToken())
                ->post($this->baseUrl().'/orders/'.rawurlencode($orderId).'/cancel');

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
     * POST /orders/{orderId}/refunds — issue a (possibly partial) refund.
     *
     * @param  array<string, mixed>  $payload  {order_id, total_amount: {amount, currency}, ...}
     * @return array<string, mixed>
     */
    public function refundPayment(string $orderId, array $payload): array
    {
        try {
            $response = $this->baseHttp()
                ->withToken($this->apiToken())
                ->post($this->baseUrl().'/orders/'.rawurlencode($orderId).'/refunds', $payload);

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

    public function notificationToken(): string
    {
        return (string) ($this->config['notification_token'] ?? '');
    }

    public function defaultPaymentType(): string
    {
        return (string) ($this->config['default_payment_type'] ?? 'PAY_BY_INSTALMENTS');
    }

    /**
     * @return array{success?: string, failure?: string, cancel?: string, notification?: string}
     */
    public function merchantUrls(): array
    {
        return (array) ($this->config['urls'] ?? []);
    }

    public function country(): string
    {
        return (string) ($this->config['country'] ?? 'SA');
    }

    public function currency(): string
    {
        return (string) ($this->config['currency'] ?? 'SAR');
    }

    public function locale(): string
    {
        return (string) ($this->config['locale'] ?? 'en_US');
    }

    private function apiToken(): string
    {
        return (string) ($this->config['api_token'] ?? '');
    }
}
