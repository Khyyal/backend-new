<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Support\Support\PhoneNumber;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;
use Modules\Support\WhatsApp\WhatsAppSendException;
use Modules\Support\WhatsApp\WhatsAppTemplateMessage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.voxa', [
        'base_uri' => 'https://api.voxa.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'channel_id' => 'channel-1',
        'timeout' => 5,
        'per_minute' => 600,
    ]);

    $this->message = new WhatsAppTemplateMessage('966501234567', 'weekend_offer', 'ar', ['Ali', 'RIDE20']);
});

test('sends a template with a cached access token', function (): void {
    Http::fake([
        'api.voxa.test/connect/token' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'api.voxa.test/v1/whatsapp/service-account/sendTemplate' => Http::response(['messageId' => 'msg-1']),
    ]);

    $gateway = app(WhatsAppGateway::class);
    $first = $gateway->sendTemplate($this->message);
    $gateway->sendTemplate($this->message);

    expect($first->messageId)->toBe('msg-1');
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/connect/token')
        && $request['grant_type'] === 'client_credentials'
        && $request['scope'] === 'VoxaAPI');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendTemplate')
        && $request->hasHeader('Authorization', 'Bearer token-1')
        && $request->data() === [
            'ChannelId' => 'channel-1',
            'ToNumber' => '966501234567',
            'TemplateName' => 'weekend_offer',
            'Language' => 'ar',
            'Parameters' => ['Ali', 'RIDE20'],
        ]);
});

test('refreshes the token once when voxa returns 401', function (): void {
    Http::fake([
        'api.voxa.test/connect/token' => Http::sequence()
            ->push(['access_token' => 'expired', 'expires_in' => 3600])
            ->push(['access_token' => 'fresh', 'expires_in' => 3600]),
        'api.voxa.test/v1/whatsapp/service-account/sendTemplate' => Http::sequence()
            ->push([], 401)
            ->push(['messageId' => 'msg-2']),
    ]);

    expect(app(WhatsAppGateway::class)->sendTemplate($this->message)->messageId)->toBe('msg-2');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendTemplate')
        && $request->hasHeader('Authorization', 'Bearer fresh'));
});

test('a rejected message raises a non-fatal exception with the response', function (): void {
    Http::fake([
        'api.voxa.test/connect/token' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'api.voxa.test/v1/whatsapp/service-account/sendTemplate' => Http::response(['error' => 'Template not approved'], 400),
    ]);

    try {
        app(WhatsAppGateway::class)->sendTemplate($this->message);
        $this->fail('Expected WhatsAppSendException.');
    } catch (WhatsAppSendException $e) {
        expect($e->status)->toBe(400)
            ->and($e->isFatal())->toBeFalse()
            ->and($e->responseBody)->toContain('Template not approved');
    }
});

test('missing credentials are a fatal error', function (): void {
    config()->set('services.voxa.client_id', null);
    Http::fake();

    expect(fn () => app(WhatsAppGateway::class)->sendTemplate($this->message))
        ->toThrow(fn (WhatsAppSendException $e) => expect($e->isFatal())->toBeTrue());
    Http::assertNothingSent();
});

test('phone numbers are normalized to international digits', function (?string $input, ?string $expected): void {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    ['+966 50 123 4567', '966501234567'],
    ['00966501234567', '966501234567'],
    ['0501234567', '966501234567'],
    ['501234567', '966501234567'],
    ['+201001234567', '201001234567'],
    ['abc', null],
    [null, null],
]);
