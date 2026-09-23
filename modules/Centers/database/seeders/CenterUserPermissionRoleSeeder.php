<?php

namespace Modules\Centers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Enums\CenterUserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CenterUserPermissionRoleSeeder extends Seeder
{
    private const GUARD = 'center_user';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = self::GUARD;

        $permissions = collect(CenterUserPermission::all())->map(
            fn (string $name): Permission => Permission::findOrCreate($name, $guard)
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();





        $ownerPermissions = CenterUserPermission::all();



        $owner = Role::findOrCreate(CenterUserRole::Owner->value, $guard);
        $owner->givePermissionTo($ownerPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
