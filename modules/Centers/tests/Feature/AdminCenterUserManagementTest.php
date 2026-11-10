<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\User;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);

    $this->center = CenterFactory::new()->create();
    $this->trainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    $this->owner = UserFactory::new()->create(['name' => 'Zaid Owner', 'phone' => '0551110001', 'phone_verified_at' => now()]);
    $this->staff = UserFactory::new()->create(['name' => 'Amal Staff', 'phone' => '0551110002', 'phone_verified_at' => now()]);

    $assignment = ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString()];
    $this->center->users()->attach($this->owner->id, [...$assignment, 'is_primary' => true]);
    $this->center->users()->attach($this->staff->id, [...$assignment, 'is_primary' => false, 'trainer_id' => $this->trainer->id]);
    $this->staff->syncCenterPermissions($this->center, [CenterUserPermission::ViewRatings]);

    $this->base = "/api/v1/admin/centers/{$this->center->id}/users";
    $this->viewer = SystemUserFactory::new()->withPermissions([SystemPermission::ViewCenter])->create();
    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ViewCenter, SystemPermission::UpdateCenter])->create();
});

test('guests are unauthenticated and viewers cannot change users', function (): void {
    $this->postJson($this->base, [])->assertUnauthorized();

    $this->actingAs($this->viewer, 'system_user');

    $this->postJson($this->base, ['name' => 'X', 'phone' => '0500000000', 'trainer_id' => $this->trainer->id, 'permissions' => []])->assertForbidden();
    $this->patchJson("{$this->base}/{$this->staff->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("{$this->base}/{$this->staff->id}")->assertForbidden();

    $this->actingAs(SystemUserFactory::new()->withPermissions([SystemPermission::UpdateCenter])->create(), 'system_user')
        ->getJson($this->base)->assertForbidden();
});

test('viewers list users with trainer and permissions, and the grantable permissions', function (): void {
    $this->actingAs($this->viewer, 'system_user');

    $this->getJson($this->base)
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->owner->id)
        ->assertJsonPath('data.0.permissions', CenterUserPermission::all())
        ->assertJsonPath('data.1.trainer.id', $this->trainer->id)
        ->assertJsonPath('data.1.permissions', [CenterUserPermission::ViewRatings->value]);

    $this->getJson("{$this->base}/{$this->staff->id}")->assertOk()->assertJsonPath('data.name', 'Amal Staff');
    $this->getJson("{$this->base}/permissions")->assertOk()->assertJsonPath('data', CenterUserPermission::all());
});

test('admins add a user with any permissions, and a phone already in the center is rejected', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $otherTrainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);

    $this->postJson($this->base, [
        'name' => 'Sara',
        'phone' => '0511111111',
        'trainer_id' => $otherTrainer->id,
        'permissions' => [CenterUserPermission::ManageUsers->value, CenterUserPermission::ViewPayouts->value],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sara')
        ->assertJsonPath('data.is_primary', false)
        ->assertJsonPath('data.trainer.id', $otherTrainer->id)
        ->assertJsonPath('data.permissions', [CenterUserPermission::ManageUsers->value, CenterUserPermission::ViewPayouts->value]);

    $this->postJson($this->base, [
        'name' => 'Dup',
        'phone' => '0551110002',
        'trainer_id' => TrainerFactory::new()->create(['center_id' => $this->center->id])->id,
        'permissions' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('phone');
});

test('admins update a staff user, and a new phone clears verification and revokes tokens', function (): void {
    $this->staff->createToken('device');
    $this->actingAs($this->admin, 'system_user');

    $this->patchJson("{$this->base}/{$this->staff->id}", [
        'name' => 'Amal Renamed',
        'phone' => '0559999999',
        'status' => ActivationStatus::INACTIVE->value,
        'permissions' => [CenterUserPermission::ManageReservations->value],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Amal Renamed')
        ->assertJsonPath('data.phone', '0559999999')
        ->assertJsonPath('data.status', ActivationStatus::INACTIVE->value)
        ->assertJsonPath('data.phone_verified_at', null)
        ->assertJsonPath('data.permissions', [CenterUserPermission::ManageReservations->value]);

    expect($this->staff->tokens()->count())->toBe(0);

    $this->patchJson("{$this->base}/{$this->staff->id}", ['phone' => '0551110001'])
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
});

test('admins edit only the name and phone of the primary user', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $this->patchJson("{$this->base}/{$this->owner->id}", ['name' => 'Zaid New', 'phone' => '0558888888'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Zaid New')
        ->assertJsonPath('data.is_primary', true);

    $this->patchJson("{$this->base}/{$this->owner->id}", ['permissions' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('user');

    $this->patchJson("{$this->base}/{$this->owner->id}", ['status' => ActivationStatus::INACTIVE->value])
        ->assertUnprocessable()->assertJsonValidationErrors('user');
});

test('admins remove staff but not the primary user', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $this->deleteJson("{$this->base}/{$this->owner->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('user');

    $this->deleteJson("{$this->base}/{$this->staff->id}")->assertNoContent();

    expect($this->center->users()->whereKey($this->staff->id)->exists())->toBeFalse()
        ->and(User::withTrashed()->find($this->staff->id)->trashed())->toBeTrue();
});

test('a user of another center is not found', function (): void {
    $stranger = UserFactory::new()->create();
    CenterFactory::new()->create()->users()->attach($stranger->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);

    $this->actingAs($this->admin, 'system_user');

    $this->getJson("{$this->base}/{$stranger->id}")->assertNotFound();
    $this->patchJson("{$this->base}/{$stranger->id}", ['name' => 'X'])->assertNotFound();
    $this->deleteJson("{$this->base}/{$stranger->id}")->assertNotFound();
});
