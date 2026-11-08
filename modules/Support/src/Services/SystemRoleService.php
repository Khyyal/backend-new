<?php

namespace Modules\Support\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Support\Models\SystemRole;
use Modules\Support\Models\SystemUser;

class SystemRoleService
{
    /**
     * @param  array{name: string, description?: string|null, permissions?: array<int, string>}  $data
     *
     * @throws ValidationException When the actor grants a permission they do not hold.
     */
    public function create(SystemUser $actor, array $data): SystemRole
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $this->ensureCanGrant($actor, $permissions);

        return DB::transaction(function () use ($data, $permissions): SystemRole {
            $role = SystemRole::query()->create($data);
            $role->syncPermissions($permissions);

            return $role;
        });
    }

    /**
     * Partial update; `permissions` (when sent) replaces the role's permissions.
     *
     * @param  array{name?: string, description?: string|null, permissions?: array<int, string>}  $data
     *
     * @throws ValidationException When the actor grants a permission they do not hold.
     */
    public function update(SystemUser $actor, SystemRole $role, array $data): SystemRole
    {
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        if ($permissions !== null) {
            $this->ensureCanGrant($actor, $permissions);
        }

        return DB::transaction(function () use ($role, $data, $permissions): SystemRole {
            $role->update($data);

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }

            return $role->refresh();
        });
    }

    /**
     * @throws ValidationException While system users still hold the role.
     */
    public function delete(SystemRole $role): void
    {
        if ($role->users()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'system_role' => [__('support::system_users.role_in_use')],
            ]);
        }

        $role->delete();
    }

    /**
     * Nobody grants a permission they do not hold themselves.
     *
     * @param  array<int, string>  $permissions
     *
     * @throws ValidationException
     */
    private function ensureCanGrant(SystemUser $actor, array $permissions): void
    {
        $missing = array_values(array_filter(
            $permissions,
            fn (string $permission): bool => ! $actor->hasPermissionTo($permission)
        ));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'permissions' => [__('support::system_users.permission_not_held', ['permissions' => implode(', ', $missing)])],
            ]);
        }
    }
}
