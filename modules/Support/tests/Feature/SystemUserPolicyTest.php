<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemRole;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds the super admin role with every permission', function () {
    $this->seed(SystemRolePermissionSeeder::class);

    $role = SystemRole::where('name', 'super_admin')->first();

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(SystemPermission::all());
});

it('lets a super admin do everything except delete themselves', function () {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->create();
    $other = SystemUser::factory()->create();

    expect(Gate::forUser($admin)->allows('viewAny', SystemUser::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', SystemUser::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $other))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('assignRoles', $other))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $other))->toBeTrue()
        ->and(Gate::forUser($admin)->denies('delete', $admin))->toBeTrue();
});

it('grants only the permissions given through roles', function () {
    $user = SystemUser::factory()
        ->withPermissions([SystemPermission::ViewAnySystemUsers, SystemPermission::ViewSystemUser])
        ->create();
    $other = SystemUser::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', SystemUser::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $other))->toBeTrue()
        ->and(Gate::forUser($user)->denies('create', SystemUser::class))->toBeTrue()
        ->and(Gate::forUser($user)->denies('update', $other))->toBeTrue()
        ->and(Gate::forUser($user)->denies('delete', $other))->toBeTrue();
});

it('lets a user view and update themselves without permissions', function () {
    $user = SystemUser::factory()->create();

    expect(Gate::forUser($user)->allows('view', $user))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $user))->toBeTrue()
        ->and(Gate::forUser($user)->denies('delete', $user))->toBeTrue();
});

it('denies everything to inactive system users', function () {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->inactive()->create();

    expect(Gate::forUser($admin)->denies('viewAny', SystemUser::class))->toBeTrue()
        ->and(Gate::forUser($admin)->denies('view', $admin))->toBeTrue();
});

it('does not leak permissions from center user roles', function () {
    $user = SystemUser::factory()->create();

    expect($user->hasPermissionTo(SystemPermission::ViewAnySystemUsers))->toBeFalse()
        ->and($user->isSuperAdmin())->toBeFalse();
});
