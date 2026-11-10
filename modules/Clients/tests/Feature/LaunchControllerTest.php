<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Models\Client;
use Modules\Support\Database\Factories\DeviceFactory;
use Modules\Support\Models\Device;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->app->instance(SMSService::class, Mockery::mock(SMSService::class));

    $this->devPhone = '+966500000999';
    $this->devCode = '1234';
    config()->set('otp.dev.enabled', true);
    config()->set('otp.dev.phones', [$this->devPhone]);
    config()->set('otp.dev.code', $this->devCode);
});

test('launch registers an unowned device', function (): void {
    $response = $this->postJson('/api/v1/clients/launch', [
        'device_identifier' => 'device-1',
        'platform' => 'ios',
        'fcm_token' => 'token-1',
        'locale' => 'ar',
    ]);

    $response->assertOk()->assertJsonPath('device.linked', false);

    $this->assertDatabaseHas('devices', [
        'device_identifier' => 'device-1',
        'platform' => 'ios',
        'fcm_token' => 'token-1',
        'locale' => 'ar',
        'deviceable_id' => null,
    ]);
});

test('repeated launch updates the same device', function (): void {
    $payload = ['device_identifier' => 'device-1', 'platform' => 'android', 'fcm_token' => 'old'];

    $this->postJson('/api/v1/clients/launch', $payload)->assertOk();
    $this->postJson('/api/v1/clients/launch', [...$payload, 'fcm_token' => 'new'])->assertOk();

    expect(Device::query()->where('device_identifier', 'device-1')->count())->toBe(1);
    expect(Device::query()->value('fcm_token'))->toBe('new');
});

test('launch validates device identifier and platform', function (): void {
    $this->postJson('/api/v1/clients/launch', ['platform' => 'windows'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_identifier', 'platform']);
});

test('verify links the launched device to the client', function (): void {
    $this->postJson('/api/v1/clients/launch', [
        'device_identifier' => 'device-1',
        'platform' => 'ios',
        'fcm_token' => 'token-1',
    ])->assertOk();

    $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
        'device_identifier' => 'device-1',
        'platform' => 'ios',
    ])->assertOk();

    $client = Client::query()->where('phone_number', $this->devPhone)->firstOrFail();
    $device = Device::query()->sole();

    expect($device->deviceable->is($client))->toBeTrue();
    expect($device->fcm_token)->toBe('token-1');
});

test('verify merges the launched device into the client existing device', function (): void {
    $client = Client::factory()->create(['phone_number' => $this->devPhone]);
    DeviceFactory::new()->ownedBy($client)->create([
        'device_identifier' => 'device-1',
        'platform' => 'ios',
        'fcm_token' => 'stale',
    ]);

    $this->postJson('/api/v1/clients/launch', [
        'device_identifier' => 'device-1',
        'platform' => 'ios',
        'fcm_token' => 'fresh',
    ])->assertOk();

    $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
        'device_identifier' => 'device-1',
    ])->assertOk();

    $device = Device::query()->sole();

    expect($device->deviceable->is($client))->toBeTrue();
    expect($device->fcm_token)->toBe('fresh');
});

test('verify without prior launch creates an owned device', function (): void {
    $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => $this->devCode,
        'device_identifier' => 'device-1',
        'platform' => 'android',
    ])->assertOk();

    $device = Device::query()->sole();

    expect($device->deviceable)->toBeInstanceOf(Client::class);
    expect($device->platform->value)->toBe('android');
});

test('authenticated launch updates the client device', function (): void {
    $client = Client::factory()->create();
    $token = $client->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/clients/launch', [
        'device_identifier' => 'device-1',
        'platform' => 'ios',
        'fcm_token' => 'token-1',
    ])->assertOk()->assertJsonPath('device.linked', true);

    expect(Device::query()->sole()->deviceable->is($client))->toBeTrue();
});
