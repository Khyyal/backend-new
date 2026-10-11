<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Enums\SystemRole;
use Modules\Support\Models\SystemRole as SystemRoleModel;
use Modules\Support\Models\SystemUser;

/**
 * @extends Factory<SystemUser>
 */
class SystemUserFactory extends Factory
{
    protected $model = SystemUser::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => ActivationStatus::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => ActivationStatus::INACTIVE]);
    }

    public function superAdmin(): static
    {
        return $this->afterCreating(function (SystemUser $user): void {
            $role = SystemRoleModel::firstOrCreate(['name' => SystemRole::SuperAdmin->value]);
            $user->roles()->syncWithoutDetaching([$role->getKey()]);
        });
    }

    /**
     * @param  array<int, SystemPermission>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(function (SystemUser $user) use ($permissions): void {
            $role = SystemRoleModel::create(['name' => 'test_role_'.fake()->unique()->lexify('????????')]);

            foreach ($permissions as $permission) {
                $model = \Modules\Support\Models\SystemPermission::firstOrCreate(['name' => $permission->value]);
                $role->permissions()->syncWithoutDetaching([$model->getKey()]);
            }

            $user->roles()->attach($role);
        });
    }
}
