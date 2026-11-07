<?php

namespace Modules\Purchase\Services;

use Illuminate\Http\Request;

/**
 * Checks that a webhook really comes from the provider. Without a configured secret every call fails.
 */
class WebhookAuthenticator
{
    /**
     * Moyasar repeats the webhook secret in the body as `secret_token`.
     */
    public function moyasar(Request $request): bool
    {
        $secret = (string) config('purchase.providers.moyasar.webhook_secret', '');
        $token = $request->input('secret_token');

        return $secret !== '' && is_string($token) && hash_equals($secret, $token);
    }

    /**
     * Tamara signs a JWT (HS256) with the notification token and sends it as a Bearer token or `tamaraToken`.
     */
    public function tamara(Request $request): bool
    {
        $key = (string) config('purchase.providers.tamara.notification_token', '');
        $jwt = $request->bearerToken() ?? $request->query('tamaraToken');

        if ($key === '' || ! is_string($jwt)) {
            return false;
        }

        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        $decodedHeader = json_decode($this->base64UrlDecode($header), true);

        if (! is_array($decodedHeader) || ($decodedHeader['alg'] ?? null) !== 'HS256') {
            return false;
        }

        $expected = hash_hmac('sha256', $header.'.'.$payload, $key, true);

        if (! hash_equals($expected, $this->base64UrlDecode($signature))) {
            return false;
        }

        $claims = json_decode($this->base64UrlDecode($payload), true);

        return is_array($claims) && (! isset($claims['exp']) || (int) $claims['exp'] > time());
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), false);
    }
}
