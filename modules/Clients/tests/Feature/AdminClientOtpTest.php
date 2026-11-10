<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->sms = Mockery::mock(SMSService::class);
    $this->app->instance(SMSService::class, $this->sms);
    $this->client = ClientFactory::new()->create(['phone_number' => '+966500000077']);
    $this->url = "/api/v1/admin/clients/{$this->client->id}/otp";
});

test('guests are unauthenticated and admins without otps.create are forbidden', function (): void {
    $this->postJson($this->url)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewClient])->create(), 'system_user')
        ->postJson($this->url)->assertForbidden();

    $this->actingAs(SystemUserFactory::new()->inactive()->withPermissions([SystemPermission::CreateOtp])->create(), 'system_user')
        ->postJson($this->url)->assertForbidden();
});

test('issues a code without sms that logs the client in', function (): void {
    $this->freezeTime();
    $this->sms->shouldNotReceive('sendOTP');

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::CreateOtp])->create(), 'system_user');

    $response = $this->postJson($this->url)
        ->assertCreated()
        ->assertJsonPath('data.phone', '+966500000077')
        ->assertJsonPath('data.expires_in_seconds', 300);

    $code = $response->json('data.code');
    expect($code)->toMatch('/^\d{4}$/');

    $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => '+966500000077',
        'code' => $code,
    ])->assertOk()->assertJsonPath('client.id', $this->client->id);
});

test('returns 404 for an unknown client', function (): void {
    $this->actingAs(SystemUserFactory::new()->superAdmin()->create(), 'system_user')
        ->postJson('/api/v1/admin/clients/999999/otp')
        ->assertNotFound();
});
