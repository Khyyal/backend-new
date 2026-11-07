<?php

namespace Modules\Support\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemRole as SystemRoleEnum;
use Modules\Support\Models\SystemRole;
use Modules\Support\Models\SystemUser;

class SuperAdminSeeder extends Seeder
{
    /**
     * Creates (or refreshes) the super admin from `support.super_admin`.
     */
    public function run(): void
    {
        $email = config('support.super_admin.email');
        $password = config('support.super_admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('Super admin not seeded: set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD.');

            return;
        }

        $user = SystemUser::query()->updateOrCreate(['email' => $email], [
            'name' => config('support.super_admin.name'),
            'password' => $password,
            'status' => ActivationStatus::ACTIVE,
        ]);

        $role = SystemRole::firstOrCreate(['name' => SystemRoleEnum::SuperAdmin->value]);
        $user->roles()->syncWithoutDetaching([$role->getKey()]);
    }
}
