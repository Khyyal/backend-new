<?php

namespace Modules\Support\WhatsApp;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;

/**
 * Voxa WhatsApp service-account API (OAuth2 client credentials + template / text send).
 */
final class VoxaWhatsAppGateway implements WhatsAppGateway
{
    private const string TOKEN_CACHE_KEY = 'voxa:access_token';

    /**
     * @param  array{base_uri: string, client_id: ?string, client_secret: ?string, channel_id: ?string, timeout: int}  $config
     */
    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    public function sendTemplate(WhatsAppTemplateMessage $message): WhatsAppSendResult
    {
        return $this->send('sendTemplate', [
            'ChannelId' => $this->config['channel_id'],
            'ToNumber' => $message->toNumber,
            'TemplateName' => $message->templateName,
            'Language' => $message->language,
            'Parameters' => array_values($message->parameters),
        ]);
    }

    public function sendText(string $toNumber, string $text): WhatsAppSendResult
    {
        return $this->send('sendText', [
            'ChannelId' => $this->config['channel_id'],
            'ToNumber' => $toNumber,
            'Text' => $text,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $endpoint, array $payload): WhatsAppSendResult
    {
        $url = rtrim($this->config['base_uri'], '/').'/v1/whatsapp/service-account/'.$endpoint;

        $response = $this->post($url, $payload, $this->accessToken());

        if ($response->status() === 401) {
            $this->cache->forget(self::TOKEN_CACHE_KEY);
            $response = $this->post($url, $payload, $this->accessToken());
        }

        if ($response->failed()) {
            throw new WhatsAppSendException("Voxa {$endpoint} failed.", $response->status(), $response->body());
        }

        $body = $response->json() ?? [];

        return new WhatsAppSendResult(
            messageId: $this->messageId($body),
            response: is_array($body) ? $body : [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $url, array $payload, string $token): Response
    {
        try {
            return $this->request()
                ->withToken($token)
                ->retry(2, 200, fn ($exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new WhatsAppSendException('Voxa is unreachable.', null, null, $e);
        }
    }

    private function accessToken(): string
    {
        $cached = $this->cache->get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (blank($this->config['client_id']) || blank($this->config['client_secret'])) {
            throw new WhatsAppSendException('Voxa credentials are not configured.');
        }

        try {
            $response = $this->request()
                ->asForm()
                ->post(rtrim($this->config['base_uri'], '/').'/connect/token', [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'grant_type' => 'client_credentials',
                    'scope' => 'VoxaAPI',
                ]);
        } catch (ConnectionException $e) {
            throw new WhatsAppSendException('Voxa is unreachable.', null, null, $e);
        }

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new WhatsAppSendException('Voxa token request failed.', $response->failed() ? $response->status() : 401, $response->body());
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 60);
        $this->cache->put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function request(): PendingRequest
    {
        return $this->http->acceptJson()->timeout($this->config['timeout']);
    }

    /**
     * @param  mixed  $body
     */
    private function messageId($body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $id = $body['messageId'] ?? $body['MessageId'] ?? $body['id'] ?? $body['data']['messageId'] ?? null;

        return $id === null ? null : (string) $id;
    }
}
