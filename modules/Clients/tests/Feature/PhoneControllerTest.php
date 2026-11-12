<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Models\OTP;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->smsService = Mockery::mock(SMSService::class);
    $this->app->instance(SMSService::class, $this->smsService);

    $this->newPhone = '+966500000999';
    $this->devCode = '1234';
    config()->set('otp.dev.enabled', true);
    config()->set('otp.dev.phones', [$this->newPhone]);
    config()->set('otp.dev.code', $this->devCode);

    $this->client = ClientFactory::new()->create(['phone_number' => '+966500000001']);
});

test('changing the phone requires a client token', function (): void {
    $this->postJson('/api/v1/clients/phone', ['phone_number' => $this->newPhone])->assertUnauthorized();
    $this->postJson('/api/v1/clients/phone/verify', ['phone_number' => $this->newPhone, 'code' => $this->devCode])
        ->assertUnauthorized();
});

test('sends a code to the new phone by sms', function (): void {
    $phone = '+966501234567';
    $this->smsService->shouldReceive('sendOTP')->once()->with($phone, Mockery::type('string'));

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/phone', ['phone_number' => $phone])
        ->assertOk()
        ->assertJsonPath('expires_in_seconds', 300);

    expect(OTP::query()->where('phone_number', $phone)->count())->toBe(1);
});

test('no code is sent to an unusable phone', function (string $phone): void {
    ClientFactory::new()->create(['phone_number' => '+966500000002']);
    $this->smsService->shouldNotReceive('sendOTP');

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/phone', ['phone_number' => $phone])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone_number');
})->with([
    'my current phone' => '+966500000001',
    'another client\'s phone' => '+966500000002',
    'a malformed phone' => 'not-a-phone',
]);

test('the right code moves me to the new phone', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/phone/verify', ['phone_number' => $this->newPhone, 'code' => $this->devCode])
        ->assertOk()
        ->assertJsonPath('client.id', $this->client->id)
        ->assertJsonPath('client.phone_number', $this->newPhone);

    expect($this->client->refresh()->phone_number)->toBe($this->newPhone);

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/me')
        ->assertOk()
        ->assertJsonPath('data.phone_number', $this->newPhone);
});

test('a wrong code leaves my phone unchanged', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/phone/verify', ['phone_number' => $this->newPhone, 'code' => '0000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($this->client->refresh()->phone_number)->toBe('+966500000001');
});

test('a phone taken by another client cannot be confirmed', function (): void {
    ClientFactory::new()->create(['phone_number' => $this->newPhone]);

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/phone/verify', ['phone_number' => $this->newPhone, 'code' => $this->devCode])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone_number');

    expect($this->client->refresh()->phone_number)->toBe('+966500000001');
});

test('a phone held only by a deleted client can be taken', function (): void {
    ClientFactory::new()->create(['phone_number' => $this->newPhone])->delete();

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/phone/verify', ['phone_number' => $this->newPhone, 'code' => $this->devCode])
        ->assertOk();

    expect($this->client->refresh()->phone_number)->toBe($this->newPhone);
});
