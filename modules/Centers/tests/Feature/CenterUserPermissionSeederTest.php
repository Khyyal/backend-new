<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Seeders\CenterUserPermissionRoleSeeder;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Enums\CenterUserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('seeds every center permission, drops stale ones and grants them all to the owner role', function (): void {
    Permission::findOrCreate('manage_services', 'center_user');

    $this->seed(CenterUserPermissionRoleSeeder::class);

    expect(Permission::query()->where('guard_name', 'center_user')->pluck('name')->sort()->values()->all())
        ->toBe(collect(CenterUserPermission::all())->sort()->values()->all());

    expect(Role::findByName(CenterUserRole::Owner->value, 'center_user')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(CenterUserPermission::all())->sort()->values()->all());
});
