<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Support\Enums\SystemPermission as SystemPermissionEnum;
use Modules\Support\Enums\SystemRole as SystemRoleEnum;
use Modules\Support\Models\SystemRole;

trait HasSystemRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            SystemRole::class,
            'system_role_user',
            'system_user_id',
            'system_role_id'
        );
    }

    public function hasRole(SystemRoleEnum|string $role): bool
    {
        $name = $role instanceof SystemRoleEnum ? $role->value : $role;

        return $this->roles->contains('name', $name);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(SystemRoleEnum::SuperAdmin);
    }

    public function hasPermissionTo(SystemPermissionEnum|string $permission): bool
    {
        $name = $permission instanceof SystemPermissionEnum ? $permission->value : $permission;

        return $this->loadMissing('roles.permissions')
            ->roles
            ->contains(fn (SystemRole $role): bool => $role->hasPermission($name));
    }

    /**
     * @param  array<int, SystemRoleEnum|string>  $roles
     */
    public function assignRoles(array $roles): static
    {
        $names = array_map(
            fn (SystemRoleEnum|string $role): string => $role instanceof SystemRoleEnum ? $role->value : $role,
            $roles
        );

        $this->roles()->syncWithoutDetaching(SystemRole::whereIn('name', $names)->pluck('id'));
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * @param  array<int, SystemRoleEnum|string>  $roles
     */
    public function syncRoles(array $roles): static
    {
        $names = array_map(
            fn (SystemRoleEnum|string $role): string => $role instanceof SystemRoleEnum ? $role->value : $role,
            $roles
        );

        $this->roles()->sync(SystemRole::whereIn('name', $names)->pluck('id'));
        $this->unsetRelation('roles');

        return $this;
    }
}
