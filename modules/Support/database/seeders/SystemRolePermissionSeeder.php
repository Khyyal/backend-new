<?php

namespace Modules\Support\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Support\Enums\SystemPermission as SystemPermissionEnum;
use Modules\Support\Enums\SystemRole as SystemRoleEnum;
use Modules\Support\Models\SystemPermission;
use Modules\Support\Models\SystemRole;

class SystemRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemPermissionEnum::all() as $name) {
            SystemPermission::firstOrCreate(['name' => $name]);
        }

        SystemRole::firstOrCreate(['name' => SystemRoleEnum::SuperAdmin->value])
            ->givePermissionTo(SystemPermissionEnum::cases());
    }
}
