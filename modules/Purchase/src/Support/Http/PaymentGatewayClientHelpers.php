<?php

namespace Modules\Purchase\Support\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Purchase\Exceptions\PaymentGatewayException;
use Modules\Purchase\Exceptions\PaymentInitializationException;
use Modules\Purchase\Exceptions\PaymentProviderUnavailableException;
use Modules\Purchase\Exceptions\PaymentProviderValidationException;
use Modules\Purchase\Exceptions\PaymentStateConflictException;
use Throwable;

trait PaymentGatewayClientHelpers
{
    protected int $connectTimeout = 5;

    protected int $timeout = 15;

    /**
     * Build a base HTTP client with default headers and timeouts.
     * Middleware redacts Authorization headers and sensitive body fields before logging.
     */
    protected function baseHttp(): PendingRequest
    {
        return Http::connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withMiddleware(function (callable $handler): callable {
                return function ($request, $options) use ($handler) {
                    $this->logOutgoingRequest($request, $options);

                    return $handler($request, $options)->then(function ($response) use ($request) {
                        $this->logIncomingResponse($request, $response);

                        return $response;
                    });
                };
            });
    }

    /**
     * Convert raw HTTP exceptions into the normalized PaymentGatewayException hierarchy.
     * Mapping:
     *  - 422 (or 4xx with validation shape) → PaymentProviderValidationException
     *  - 409 → PaymentStateConflictException
     *  - 5xx / ConnectionException / timeout → PaymentProviderUnavailableException
     *  - Everything else → generic PaymentGatewayException
     *
     * Sensitive tokens/PAN/CVC are never placed in the exception message.
     *
     * @template T of Throwable
     *
     * @param  T  $e
     * @param  class-string<PaymentGatewayException>  $defaultClass
     * @return never-return
     *
     * @throws PaymentGatewayException
     */
    protected function convertRequestException(
        Throwable $e,
        string $defaultClass = PaymentGatewayException::class,
    ): void {
        $status = 0;
        if ($e instanceof RequestException) {
            try {
                $status = $e->response ? $e->response->status() : 0;
            } catch (Throwable) {
                $status = 0;
            }
        }

        $message = 'Payment gateway request failed (provider error details redacted).';
        $matchClass = $defaultClass;

        if ($status >= 500 || $status === 0) {
            $matchClass = PaymentProviderUnavailableException::class;
        } elseif ($status === 422 || $status === 400) {
            $matchClass = PaymentProviderValidationException::class;
        } elseif ($status === 409) {
            $matchClass = PaymentStateConflictException::class;
        }

        $this->logRedactedException($e, $status);

        throw new $matchClass($message, (int) $status, $e);
    }

    /**
     * Wrap initialize() operations. Retries are permitted (retry-initialize-only rule);
     * capture/refund/void callers must NOT use this helper.
     *
     * @template R
     *
     * @param  callable(): R  $operation
     * @param  int  $maxRetries
     * @return R
     */
    protected function withInitializeRetry(callable $operation, int $maxRetries = 2)
    {
        $attempts = 0;
        $last = null;

        while ($attempts <= $maxRetries) {
            try {
                return $operation();
            } catch (PaymentProviderUnavailableException $e) {
                $last = $e;
                $attempts++;
                if ($attempts > $maxRetries) {
                    break;
                }
                usleep(100000 * $attempts);
            }
        }

        throw $last ?? new PaymentInitializationException('Initialize retries exhausted.');
    }

    /**
     * Redact sensitive fields from a request payload or log context.
     */
    protected function redact(array $data): array
    {
        $sensitiveKeys = [
            'authorization', 'token', 'api_token', 'api_key', 'secret', 'secret_key',
            'publishable_key', 'notification_token', 'password', 'card', 'pan', 'cvc',
            'cvv', 'number', 'account',
        ];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $redacted = false;

            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $data[$key] = '[REDACTED]';
                    $redacted = true;

                    break;
                }
            }

            if ($redacted) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /**
     * Log an outgoing request with redacted headers/body.
     *
     * @param  mixed  $request
     * @param  array<string, mixed>  $options
     */
    private function logOutgoingRequest($request, array $options): void
    {
        if (! function_exists('env') || env('APP_ENV', 'production') === 'testing') {
            return;
        }

        try {
            $headers = $this->redact(method_exists($request, 'headers') ? $request->headers() : []);
            $body = $this->redact(method_exists($request, 'body') ? (array) $request->body() : []);

            Log::debug('[PaymentGateway] → outgoing request', [
                'url' => method_exists($request, 'url') ? $request->url() : 'n/a',
                'method' => method_exists($request, 'method') ? $request->method() : 'n/a',
                'headers' => $headers,
                'body' => $body,
            ]);
        } catch (Throwable) {
            // swallow — logging must never break the payment flow
        }
    }

    /**
     * Log an incoming response with redacted body.
     *
     * @param  mixed  $request
     * @param  mixed  $response
     */
    private function logIncomingResponse($request, $response): void
    {
        if (! function_exists('env') || env('APP_ENV', 'production') === 'testing') {
            return;
        }

        try {
            $status = method_exists($response, 'status') ? $response->status() : 0;
            $bodyRaw = method_exists($response, 'body') ? $response->body() : '';
            $body = json_decode($bodyRaw, true);
            $redacted = is_array($body) ? $this->redact($body) : '[REDACTED non-json]';

            Log::debug('[PaymentGateway] ← incoming response', [
                'status' => $status,
                'body' => $redacted,
            ]);
        } catch (Throwable) {
            // swallow
        }
    }

    /**
     * Log an exception (redacted) to avoid leaking provider credentials in error traces.
     */
    private function logRedactedException(Throwable $e, int $status): void
    {
        try {
            $msg = $e->getMessage();
            if (strlen($msg) > 500) {
                $msg = substr($msg, 0, 500).'...[truncated]';
            }

            Log::warning('[PaymentGateway] request exception', [
                'status' => $status,
                'class' => get_class($e),
                'message' => $this->redactString($msg),
                'code' => $e->getCode(),
            ]);
        } catch (Throwable) {
            // swallow
        }
    }

    /**
     * Strip common credential-looking tokens from free-form strings.
     */
    private function redactString(string $s): string
    {
        // Bearer tokens, basic auth base64, long hex tokens
        $patterns = [
            '/Bearer\s+[A-Za-z0-9_\-\.]{8,}/i' => 'Bearer [REDACTED]',
            '/Basic\s+[A-Za-z0-9+\/=]{8,}/i' => 'Basic [REDACTED]',
            '/(sk|pk|api|token)[_\- ][A-Za-z0-9_\-]{8,}/i' => '$1_[REDACTED]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $s = preg_replace($pattern, $replacement, $s);
        }

        return (string) $s;
    }
}
