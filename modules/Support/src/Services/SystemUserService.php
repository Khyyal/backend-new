<?php

namespace Modules\Support\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemRole as SystemRoleEnum;
use Modules\Support\Models\SystemUser;

class SystemUserService
{
    /**
     * @param  array{name: string, email: string, password: string, status?: string, roles?: array<int, string>}  $data
     *
     * @throws AuthorizationException When a non super admin grants the super admin role.
     */
    public function create(SystemUser $actor, array $data): SystemUser
    {
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if ($roles !== null) {
            $this->ensureCanGrant($actor, $roles);
        }

        return DB::transaction(function () use ($data, $roles): SystemUser {
            $user = SystemUser::query()->create($data + ['status' => ActivationStatus::ACTIVE]);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            return $user;
        });
    }

    /**
     * Partial update; `roles` (when sent) replaces the user's roles. Deactivating or a new password
     * revokes the user's tokens (except the actor's current one when they change their own password).
     *
     * @param  array{name?: string, email?: string, password?: string, status?: string, roles?: array<int, string>}  $data
     *
     * @throws AuthorizationException When a non super admin grants the super admin role.
     * @throws ValidationException When the change would leave no active super admin.
     */
    public function update(SystemUser $actor, SystemUser $user, array $data): SystemUser
    {
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if ($roles !== null) {
            $this->ensureCanGrant($actor, $roles);
        }

        $deactivates = ($data['status'] ?? null) === ActivationStatus::INACTIVE->value;
        $dropsSuperAdmin = $roles !== null && ! in_array(SystemRoleEnum::SuperAdmin->value, $roles, true);

        if (($deactivates || $dropsSuperAdmin) && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'system_user' => [__('support::system_users.last_super_admin')],
            ]);
        }

        return DB::transaction(function () use ($actor, $user, $data, $roles, $deactivates): SystemUser {
            $user->update($data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            if ($deactivates || array_key_exists('password', $data)) {
                $currentToken = $actor->currentAccessToken();

                $user->tokens()
                    ->when(
                        $actor->is($user) && $currentToken instanceof PersonalAccessToken,
                        fn ($query) => $query->whereKeyNot($currentToken->getKey())
                    )
                    ->delete();
            }

            return $user->refresh();
        });
    }

    /**
     * Soft delete and revoke every token.
     *
     * @throws ValidationException When the user is the last active super admin.
     */
    public function delete(SystemUser $user): void
    {
        if ($this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'system_user' => [__('support::system_users.last_super_admin')],
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }

    public function restore(SystemUser $user): SystemUser
    {
        $user->restore();

        return $user;
    }

    private function isLastActiveSuperAdmin(SystemUser $user): bool
    {
        return $user->isActive()
            && $user->isSuperAdmin()
            && ! SystemUser::otherActiveSuperAdminsExist($user);
    }

    /**
     * @param  array<int, string>  $roles
     *
     * @throws AuthorizationException
     */
    private function ensureCanGrant(SystemUser $actor, array $roles): void
    {
        if (in_array(SystemRoleEnum::SuperAdmin->value, $roles, true) && ! $actor->isSuperAdmin()) {
            throw new AuthorizationException(__('support::system_users.super_admin_only'));
        }
    }
}
