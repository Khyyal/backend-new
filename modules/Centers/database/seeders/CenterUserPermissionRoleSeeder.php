<?php

namespace Modules\Centers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Enums\CenterUserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every `CenterUserPermission` on the `center_user` guard, removes
 * permissions no longer defined, and gives the owner role all of them.
 */
class CenterUserPermissionRoleSeeder extends Seeder
{
    private const GUARD = 'center_user';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = CenterUserPermission::all();

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereNotIn('name', $permissions)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(CenterUserRole::Owner->value, self::GUARD)
            ->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
