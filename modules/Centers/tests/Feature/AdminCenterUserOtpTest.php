<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->sms = Mockery::mock(SMSService::class);
    $this->app->instance(SMSService::class, $this->sms);

    $this->center = CenterFactory::new()->create();
    $this->owner = UserFactory::new()->create(['name' => 'Zaid Owner', 'phone' => '0551110001']);
    $this->staff = UserFactory::new()->create(['name' => 'Amal Staff', 'phone' => '0551110002']);

    $assignment = ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString()];
    $this->center->users()->attach($this->owner->id, [...$assignment, 'is_primary' => true]);
    $this->center->users()->attach($this->staff->id, [...$assignment, 'is_primary' => false]);

    $this->base = "/api/v1/admin/centers/{$this->center->id}/users";
});

test('guests are unauthenticated and admins without permission are forbidden', function (): void {
    $this->getJson($this->base)->assertUnauthorized();
    $this->postJson("{$this->base}/{$this->staff->id}/otp")->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewCenter])->create(), 'system_user')
        ->postJson("{$this->base}/{$this->staff->id}/otp")->assertForbidden();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::CreateOtp])->create(), 'system_user')
        ->getJson($this->base)->assertForbidden();
});

test('lists the center users with the primary user first', function (): void {
    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::ViewCenter])->create(), 'system_user')
        ->getJson($this->base)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->owner->id)
        ->assertJsonPath('data.0.is_primary', true)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.1.phone', '0551110002');
});

test('issues a code without sms that logs the center user in', function (): void {
    $this->sms->shouldNotReceive('sendOTP');

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::CreateOtp])->create(), 'system_user');

    $code = $this->postJson("{$this->base}/{$this->staff->id}/otp")
        ->assertCreated()
        ->assertJsonPath('data.phone', '0551110002')
        ->json('data.code');

    expect($code)->toMatch('/^\d{4}$/');

    $this->postJson('/api/v1/centers/auth/phone/verify', [
        'phone' => '0551110002',
        'code' => $code,
    ])->assertOk();
});

test('returns 404 when the user belongs to another center', function (): void {
    $outsider = UserFactory::new()->create();
    CenterFactory::new()->create()->users()->attach($outsider->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => true,
    ]);

    $this->actingAs(SystemUserFactory::new()->superAdmin()->create(), 'system_user')
        ->postJson("{$this->base}/{$outsider->id}/otp")
        ->assertNotFound();
});
