<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Support\Enums\DevicePlatform;
use Modules\Support\Models\Device;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a system user registers a web device owned by them', function () {
    $user = SystemUser::factory()->create();

    $this->actingAs($user, 'system_user')
        ->postJson('/api/v1/admin/devices', [
            'device_identifier' => 'browser-1',
            'platform' => 'web',
            'fcm_token' => 'token-1',
            'locale' => 'ar',
        ])
        ->assertOk()
        ->assertJsonPath('device.platform', 'web')
        ->assertJsonPath('device.linked', true);

    $device = Device::query()->sole();

    expect($device->platform)->toBe(DevicePlatform::Web)
        ->and($device->fcm_token)->toBe('token-1')
        ->and($device->deviceable->is($user))->toBeTrue()
        ->and($user->fcmTokensByLocale())->toBe(['ar' => ['token-1']]);
});

test('registering the same device again refreshes its token', function () {
    $user = SystemUser::factory()->create();
    $payload = ['device_identifier' => 'browser-1', 'platform' => 'web', 'fcm_token' => 'old-token'];

    $this->actingAs($user, 'system_user')->postJson('/api/v1/admin/devices', $payload)->assertOk();
    $this->actingAs($user, 'system_user')
        ->postJson('/api/v1/admin/devices', [...$payload, 'fcm_token' => 'new-token'])
        ->assertOk();

    expect(Device::query()->sole()->fcm_token)->toBe('new-token');
});

test('registering a device requires authentication', function () {
    $this->postJson('/api/v1/admin/devices', [
        'device_identifier' => 'browser-1',
        'platform' => 'web',
    ])->assertUnauthorized();
});

test('registering a device validates identifier and platform', function () {
    $this->actingAs(SystemUser::factory()->create(), 'system_user')
        ->postJson('/api/v1/admin/devices', ['platform' => 'desktop'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_identifier', 'platform']);
});

test('removing a device only removes the current system user device', function () {
    $user = SystemUser::factory()->create();
    $other = SystemUser::factory()->create();
    Device::factory()->ownedBy($user)->create(['device_identifier' => 'browser-1']);
    $otherDevice = Device::factory()->ownedBy($other)->create(['device_identifier' => 'browser-1']);

    $this->actingAs($user, 'system_user')
        ->deleteJson('/api/v1/admin/devices/browser-1')
        ->assertNoContent();

    expect(Device::query()->pluck('id')->all())->toBe([$otherDevice->id]);
});
