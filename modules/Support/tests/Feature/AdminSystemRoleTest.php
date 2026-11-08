<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Support\Database\Factories\SystemRoleFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemRole;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->base = '/api/v1/admin/system-roles';
});

test('guests are unauthenticated and users without permission are forbidden', function (): void {
    $this->getJson($this->base)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson($this->base)->assertForbidden();
    $this->getJson('/api/v1/admin/system-permissions')->assertForbidden();
    $this->postJson($this->base, ['name' => 'support_agent'])->assertForbidden();
});

test('admin creates, lists, shows, updates and deletes a role', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $id = $this->postJson($this->base, [
        'name' => 'support_agent',
        'description' => 'Handles clients',
        'permissions' => ['clients.view_any', 'clients.view'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_protected', false)
        ->assertJsonPath('data.users_count', 0)
        ->assertJsonPath('data.permissions', fn (array $permissions) => collect($permissions)->sort()->values()->all() === ['clients.view', 'clients.view_any'])
        ->json('data.id');

    $this->getJson($this->base)->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("{$this->base}/{$id}")->assertOk()->assertJsonPath('data.name', 'support_agent');

    $this->patchJson("{$this->base}/{$id}", ['permissions' => ['cities.view_any']])
        ->assertOk()
        ->assertJsonPath('data.permissions', ['cities.view_any'])
        ->assertJsonPath('data.description', 'Handles clients');

    $this->deleteJson("{$this->base}/{$id}")->assertNoContent();
    expect(SystemRole::query()->find($id))->toBeNull();
});

test('protected roles cannot be updated or deleted', function (): void {
    $superAdmin = SystemRole::query()->where('name', 'super_admin')->first();
    $this->actingAs($this->admin, 'system_user');

    $this->getJson("{$this->base}/{$superAdmin->id}")->assertOk()->assertJsonPath('data.is_protected', true);
    $this->patchJson("{$this->base}/{$superAdmin->id}", ['permissions' => []])->assertForbidden();
    $this->deleteJson("{$this->base}/{$superAdmin->id}")->assertForbidden();
});

test('a role held by users cannot be deleted', function (): void {
    $role = SystemRoleFactory::new()->create();
    SystemUserFactory::new()->create()->roles()->attach($role);

    $this->actingAs($this->admin, 'system_user')
        ->deleteJson("{$this->base}/{$role->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('system_role');
});

test('nobody grants a permission they do not hold', function (): void {
    $creator = SystemUserFactory::new()->withPermissions([SystemPermission::CreateSystemRole, SystemPermission::ViewAnyCities])->create();

    $this->actingAs($creator, 'system_user');
    $this->postJson($this->base, ['name' => 'escalated', 'permissions' => ['system_users.delete']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions');
    $this->postJson($this->base, ['name' => 'city_viewer', 'permissions' => ['cities.view_any']])->assertCreated();
});

test('validates the role name and permission values', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson($this->base, ['name' => 'Super Admin!', 'permissions' => ['not.a_permission']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'permissions.0']);

    $this->postJson($this->base, ['name' => 'super_admin'])->assertUnprocessable()->assertJsonValidationErrors('name');
});

test('the permissions endpoint lists every permission grouped by resource', function (): void {
    $groups = $this->actingAs($this->admin, 'system_user')
        ->getJson('/api/v1/admin/system-permissions')
        ->assertOk()
        ->json('data');

    expect(collect($groups)->flatMap(fn (array $group) => $group['permissions'])->all())->toEqualCanonicalizing(SystemPermission::all())
        ->and(collect($groups)->firstWhere('group', 'system_users')['permissions'])->toContain('system_users.create');
});
