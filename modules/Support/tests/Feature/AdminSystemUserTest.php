<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Support\Database\Factories\SystemRoleFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;
use Modules\Support\Services\SystemUserService;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->base = '/api/v1/admin/system-users';
    $this->payload = [
        'name' => 'Sara Ops',
        'email' => 'sara@khyyal.test',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
    ];
});

test('guests are unauthenticated and users without permission are forbidden', function (): void {
    $this->getJson($this->base)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, $this->payload)->assertForbidden();
});

test('creating with roles also needs the assign roles permission', function (): void {
    $role = SystemRoleFactory::new()->create();
    $creator = SystemUserFactory::new()->withPermissions([SystemPermission::CreateSystemUser])->create();

    $this->actingAs($creator, 'system_user');
    $this->postJson($this->base, $this->payload + ['roles' => [$role->name]])->assertForbidden();
    $this->postJson($this->base, $this->payload)->assertCreated()->assertJsonPath('data.roles', []);
});

test('admin creates, lists, shows, updates, deletes and restores a system user', function (): void {
    $role = SystemRoleFactory::new()->create();
    $this->actingAs($this->admin, 'system_user');

    $id = $this->postJson($this->base, $this->payload + ['roles' => [$role->name]])
        ->assertCreated()
        ->assertJsonPath('data.email', 'sara@khyyal.test')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.roles', [$role->name])
        ->json('data.id');

    expect(Hash::check('secret-pass', SystemUser::query()->find($id)->password))->toBeTrue();
    $this->postJson('/api/v1/admin/auth/login', ['email' => 'sara@khyyal.test', 'password' => 'secret-pass'])->assertOk();

    $this->getJson("{$this->base}?search=sara")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->base}?role={$role->name}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->base}?status=inactive")->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("{$this->base}/{$id}")->assertOk()->assertJsonPath('data.name', 'Sara Ops');

    $this->patchJson("{$this->base}/{$id}", ['name' => 'Sara', 'roles' => []])
        ->assertOk()
        ->assertJsonPath('data.name', 'Sara')
        ->assertJsonPath('data.roles', []);

    SystemUser::query()->find($id)->createToken('admin');
    $this->deleteJson("{$this->base}/{$id}")->assertNoContent();
    expect(SystemUser::withTrashed()->find($id)->tokens()->count())->toBe(0);
    $this->getJson($this->base)->assertJsonCount(1, 'data');
    $this->getJson("{$this->base}?trashed=only")->assertJsonCount(1, 'data');
    $this->getJson("{$this->base}/{$id}")->assertOk()->assertJsonPath('data.deleted_at', fn ($value) => $value !== null);

    $this->postJson("{$this->base}/{$id}/restore")->assertOk()->assertJsonPath('data.deleted_at', null);

    expect(Activity::query()->where('log_name', 'system_user')->where('subject_id', $id)->pluck('event')->all())
        ->toContain('created', 'updated', 'deleted', 'restored');
});

test('deactivating or resetting the password revokes the user tokens', function (): void {
    $user = SystemUserFactory::new()->create();
    $user->createToken('admin');
    $this->actingAs($this->admin, 'system_user');

    $this->patchJson("{$this->base}/{$user->id}", ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
    expect($user->tokens()->count())->toBe(0);

    $user->update(['status' => ActivationStatus::ACTIVE]);
    $user->createToken('admin');
    $this->patchJson("{$this->base}/{$user->id}", ['password' => 'new-secret', 'password_confirmation' => 'new-secret'])->assertOk();
    expect($user->tokens()->count())->toBe(0);
});

test('a deactivated user is denied with an existing session', function (): void {
    $user = SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnySystemUsers])->inactive()->create();

    $this->actingAs($user, 'system_user')->getJson($this->base)->assertForbidden();
});

test('nobody changes their own status or roles, or deletes themselves', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $this->patchJson("{$this->base}/{$this->admin->id}", ['status' => 'inactive'])->assertForbidden();
    $this->patchJson("{$this->base}/{$this->admin->id}", ['roles' => []])->assertForbidden();
    $this->deleteJson("{$this->base}/{$this->admin->id}")->assertForbidden();
    $this->patchJson("{$this->base}/{$this->admin->id}", ['name' => 'Me'])->assertOk();
});

test('the service never removes the last active super admin', function (): void {
    $service = app(SystemUserService::class);
    $other = SystemUserFactory::new()->superAdmin()->inactive()->create();

    expect(fn () => $service->delete($this->admin))->toThrow(ValidationException::class)
        ->and(fn () => $service->update($other, $this->admin, ['status' => 'inactive']))->toThrow(ValidationException::class)
        ->and(fn () => $service->update($other, $this->admin, ['roles' => []]))->toThrow(ValidationException::class);

    $other->update(['status' => ActivationStatus::ACTIVE]);
    $service->delete($this->admin);
    expect($this->admin->refresh()->trashed())->toBeTrue();
});

test('only super admins manage super admins or grant the super admin role', function (): void {
    $manager = SystemUserFactory::new()->withPermissions([
        SystemPermission::CreateSystemUser,
        SystemPermission::UpdateSystemUser,
        SystemPermission::DeleteSystemUser,
        SystemPermission::AssignSystemUserRoles,
    ])->create();
    $plain = SystemUserFactory::new()->create();
    $this->actingAs($manager, 'system_user');

    $this->patchJson("{$this->base}/{$this->admin->id}", ['password' => 'taken-over', 'password_confirmation' => 'taken-over'])->assertForbidden();
    $this->deleteJson("{$this->base}/{$this->admin->id}")->assertForbidden();
    $this->patchJson("{$this->base}/{$plain->id}", ['roles' => ['super_admin']])->assertForbidden();
    $this->postJson($this->base, $this->payload + ['roles' => ['super_admin']])->assertForbidden();
    $this->patchJson("{$this->base}/{$plain->id}", ['name' => 'Renamed'])->assertOk();
});

test('create validates email uniqueness, password confirmation and roles', function (): void {
    SystemUserFactory::new()->create(['email' => 'gone@khyyal.test'])->delete();
    $this->actingAs($this->admin, 'system_user');

    $this->postJson($this->base, [
        'name' => 'X',
        'email' => 'gone@khyyal.test',
        'password' => 'short',
        'password_confirmation' => 'other',
        'roles' => ['unknown_role'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'roles.0']);
});
