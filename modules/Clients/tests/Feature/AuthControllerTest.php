<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);


beforeEach(function (): void {
    $this->smsService = \Mockery::mock(SMSService::class);
    $this->app->instance(SMSService::class, $this->smsService);

    $this->devPhone = '+966500000999';
    $this->devCode = '123456';
    config()->set('otp.dev.enabled', true);
    config()->set('otp.dev.phones', [$this->devPhone]);
    config()->set('otp.dev.code', $this->devCode);
});


test('login returns 422 when phone number is missing', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/login', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('phone_number');
});


test('login returns 422 when phone number has invalid format', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/login', [
        'phone_number' => 'not-a-phone',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('phone_number');
});


test('login returns 422 when phone number is too long', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/login', [
        'phone_number' => '+1234567890123456789012345678901',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('phone_number');
});


test('login succeeds for valid phone number and returns expires_in_seconds', function (): void {
    $this->smsService->shouldNotReceive('sendOTP');

    $response = $this->postJson('/api/v1/clients/auth/login', [
        'phone_number' => $this->devPhone,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'message',
        'expires_in_seconds',
    ]);
    expect($response->json('expires_in_seconds'))->toBe(300);
});


test('login sends sms for non dev phone', function (): void {
    $normalPhone = '+966501234567';
    $this->smsService
        ->shouldReceive('sendOTP')
        ->once()
        ->with($normalPhone, \Mockery::type('string'));

    $response = $this->postJson('/api/v1/clients/auth/login', [
        'phone_number' => $normalPhone,
    ]);

    $response->assertStatus(200);
});


test('verify returns 422 when required fields are missing', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/verify', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phone_number', 'code']);
});


test('verify returns 422 when code is not 6 digits', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => '123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('code');
});


test('verify returns 422 with code error for wrong otp code', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => '000000',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('code');
});


test('verify creates client and issues token for new phone', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'token',
        'token_type',
        'needs_onboarding',
        'client' => [
            'id',
            'first_name',
            'last_name',
            'phone_number',
            'needs_onboarding',
        ],
    ]);

    expect($response->json('token_type'))->toBe('Bearer');
    expect($response->json('token'))->toBeString();
    expect($response->json('needs_onboarding'))->toBeTrue();
    expect($response->json('client.phone_number'))->toBe($this->devPhone);

    $this->assertDatabaseHas('clients', [
        'phone_number' => $this->devPhone,
    ]);
});


test('verify reuses existing client for known phone', function (): void {
    $client = \Modules\Clients\Database\Factories\ClientFactory::new()->create([
        'phone_number' => $this->devPhone,
        'first_name' => 'Ali',
        'last_name' => 'Saeed',
    ]);

    $response = $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
    ]);

    $response->assertStatus(200);
    expect($response->json('needs_onboarding'))->toBeFalse();
    expect($response->json('client.id'))->toBe($client->id);
    expect($response->json('client.first_name'))->toBe('Ali');
});


test('verify registers device when device identifier header is provided', function (): void {
    $deviceId = 'test-device-abc-123';

    $response = $this->withHeaders([
        'X-Device-Identifier' => $deviceId,
    ])->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('devices', [
        'device_identifier' => $deviceId,
    ]);
});


test('verify registers device when device identifier is in body', function (): void {
    $deviceId = 'body-device-xyz';

    $response = $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
        'device_identifier' => $deviceId,
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('devices', [
        'device_identifier' => $deviceId,
    ]);
});


test('login endpoint is accessible without authentication', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/login', [
        'phone_number' => $this->devPhone,
    ]);

    $response->assertStatus(200);
});


test('verify endpoint is accessible without authentication', function (): void {
    $response = $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
    ]);

    $response->assertStatus(200);
});
