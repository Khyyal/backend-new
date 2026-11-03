<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Centers\Models\User;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Models\Device;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->app->instance(SMSService::class, Mockery::mock(SMSService::class));

    CityFactory::new()->create([
        'name' => ['en' => 'Default Test City', 'ar' => 'المدينة الافتراضية'],
    ]);

    $this->devPhone = '+966500000999';
    $this->devCode = '123456';
    config()->set('otp.dev.enabled', true);
    config()->set('otp.dev.phones', [$this->devPhone]);
    config()->set('otp.dev.code', $this->devCode);

    $this->postJson('/api/v1/centers/launch', [
        'device_identifier' => 'center-device',
        'platform' => 'android',
        'fcm_token' => 'center-token',
        'locale' => 'en',
    ])->assertOk();
});

test('launch registers an unowned center device', function (): void {
    $this->assertDatabaseHas('devices', [
        'device_identifier' => 'center-device',
        'platform' => 'android',
        'fcm_token' => 'center-token',
        'deviceable_id' => null,
    ]);
});

test('verify links the launched device to the center user', function (): void {
    $user = User::create([
        'name' => 'Center User',
        'phone' => $this->devPhone,
        'password' => Hash::make('secret1234'),
    ]);

    $this->withHeaders(['X-Device-Identifier' => 'center-device'])
        ->postJson('/api/v1/centers/auth/phone/verify', [
            'phone' => $this->devPhone,
            'code' => $this->devCode,
            'platform' => 'android',
        ])->assertOk();

    $device = Device::query()->sole();

    expect($device->deviceable->is($user))->toBeTrue();
    expect($device->fcm_token)->toBe('center-token');
});

test('register links the launched device to the new center user', function (): void {
    $this->postJson('/api/v1/centers/register', [
        'name' => 'Phoenix Learning Center',
        'contact_phone' => '+966112345678',
        'device_identifier' => 'center-device',
        'user' => [
            'name' => 'Ahmad Al-Farsi',
            'phone' => $this->devPhone,
            'code' => $this->devCode,
            'password' => 'validpassword123',
        ],
    ])->assertCreated();

    $user = User::query()->where('phone', $this->devPhone)->firstOrFail();

    expect(Device::query()->sole()->deviceable->is($user))->toBeTrue();
});
