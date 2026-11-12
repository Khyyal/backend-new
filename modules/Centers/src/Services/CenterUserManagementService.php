<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;

/**
 * Lets a center's primary user, or a user granted `users.manage`, manage the
 * other users of the center and the permissions each one holds on it. A
 * non-primary manager cannot change themselves and can only grant
 * permissions they hold.
 */
class CenterUserManagementService
{
    /**
     * @return Collection<int, User>
     */
    public function list(Center $center): Collection
    {
        $users = $center->users()
            ->orderByDesc('is_primary')
            ->orderBy('center_users.name')
            ->get();

        return $this->loadTrainers($users);
    }

    public function findMember(Center $center, int $userId): User
    {
        /** @var User $user */
        $user = $center->users()->findOrFail($userId);

        $this->loadTrainers(new Collection([$user]));

        return $user;
    }

    /**
     * Assigns a user to the center. An existing account with the same phone is
     * reused (and restored when soft-deleted) without changing its name.
     *
     * @param  array{name: string, phone: string, trainer_id: int, status?: string, permissions: array<string>}  $data
     *
     * @throws ValidationException When the phone already belongs to a user of this center,
     *                             or the actor grants a permission they lack.
     */
    public function create(Center $center, array $data, User $actor): User
    {
        $this->ensureCanGrant($center, $actor, $data['permissions']);

        return $this->assign($center, $data);
    }

    /**
     * Assigns a user to the center on behalf of a system user, who may grant
     * any permission.
     *
     * @param  array{name: string, phone: string, trainer_id: int, status?: string, permissions: array<string>}  $data
     *
     * @throws ValidationException When the phone already belongs to a user of this center.
     */
    public function createByAdmin(Center $center, array $data): User
    {
        return $this->assign($center, $data);
    }

    /**
     * @param  array{name?: string, trainer_id?: int, status?: string, permissions?: array<string>}  $data
     *
     * @throws ValidationException When the user is the center's primary user or the actor,
     *                             or the actor grants a permission they lack.
     */
    public function update(Center $center, User $user, array $data, User $actor): User
    {
        $this->ensureNotPrimary($user);
        $this->ensureNotSelf($actor, $user);
        $this->ensureCanGrant($center, $actor, $data['permissions'] ?? []);

        $this->applyChanges($center, $user, $data);

        return $this->findMember($center, $user->id);
    }

    /**
     * Updates a center user on behalf of a system user, who may grant any
     * permission and may change the phone. The primary user only accepts
     * `name` and `phone`. A new phone must be verified again and signs the
     * account out everywhere, since the account may serve several centers.
     *
     * @param  array{name?: string, phone?: string, trainer_id?: int, status?: string, permissions?: array<string>}  $data
     *
     * @throws ValidationException When changing anything but name or phone of the primary user.
     */
    public function updateByAdmin(Center $center, User $user, array $data): User
    {
        if ($user->pivot?->is_primary && array_diff_key($data, array_flip(['name', 'phone'])) !== []) {
            throw ValidationException::withMessages([
                'user' => [__('centers::validation.primary_user_identity_only')],
            ]);
        }

        $this->applyChanges($center, $user, $data);

        return $this->findMember($center, $user->id);
    }

    /**
     * Removes the user from the center: drops their permissions on it and
     * revokes their center-scoped tokens. An account left without any center
     * is signed out everywhere and soft-deleted.
     *
     * @throws ValidationException When the user is the center's primary user or the actor.
     */
    public function remove(Center $center, User $user, User $actor): void
    {
        $this->ensureNotPrimary($user);
        $this->ensureNotSelf($actor, $user);

        $this->detach($center, $user);
    }

    /**
     * Removes the user from the center on behalf of a system user.
     *
     * @throws ValidationException When the user is the center's primary user.
     */
    public function removeByAdmin(Center $center, User $user): void
    {
        $this->ensureNotPrimary($user);

        $this->detach($center, $user);
    }

    /**
     * Assigns a user to the center. An existing account with the same phone is
     * reused (and restored when soft-deleted) without changing its name.
     *
     * @param  array{name: string, phone: string, trainer_id: int, status?: string, permissions: array<string>}  $data
     *
     * @throws ValidationException When the phone already belongs to a user of this center.
     */
    private function assign(Center $center, array $data): User
    {
        $user = DB::transaction(function () use ($center, $data): User {
            /** @var User|null $user */
            $user = User::withTrashed()->where('phone', $data['phone'])->first();

            if ($user !== null && $user->centerAssignment($center) !== null) {
                throw ValidationException::withMessages([
                    'phone' => [__('centers::validation.member_already_assigned')],
                ]);
            }

            if ($user === null) {
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                ]);
            } elseif ($user->trashed()) {
                $user->restore();
            }

            $center->users()->attach($user->id, [
                'status' => $data['status'] ?? ActivationStatus::ACTIVE->value,
                'joined_at' => now()->toDateString(),
                'is_primary' => false,
                'trainer_id' => $data['trainer_id'],
            ]);

            $user->syncCenterPermissions($center, $data['permissions']);

            return $user;
        });

        return $this->findMember($center, $user->id);
    }

    /**
     * Writes the user's name and phone, their assignment's status and trainer,
     * and their permissions on the center. A changed phone clears its
     * verification and revokes every token of the account.
     *
     * @param  array{name?: string, phone?: string, trainer_id?: int, status?: string, permissions?: array<string>}  $data
     */
    private function applyChanges(Center $center, User $user, array $data): void
    {
        DB::transaction(function () use ($center, $user, $data): void {
            $user->fill(array_intersect_key($data, array_flip(['name', 'phone'])));

            if ($user->isDirty('phone')) {
                $user->phone_verified_at = null;
                $user->tokens()->delete();
            }

            $user->save();

            $pivotChanges = array_intersect_key($data, array_flip(['status', 'trainer_id']));

            if ($pivotChanges !== []) {
                $center->users()->updateExistingPivot($user->id, $pivotChanges);
            }

            if (array_key_exists('permissions', $data)) {
                $user->syncCenterPermissions($center, $data['permissions']);
            }
        });
    }

    /**
     * Cuts every user's access to a center that was just soft-deleted, while
     * keeping their assignments and permissions so the center can be restored
     * with its team. Center-scoped tokens are revoked; an account left without
     * any other center is signed out everywhere and soft-deleted.
     */
    public function suspendAll(Center $center): void
    {
        DB::transaction(function () use ($center): void {
            foreach ($center->users()->get() as $user) {
                $user->tokens()
                    ->whereJsonContains('abilities', CenterAuthService::centerAccessAbilityFor($center->id))
                    ->delete();

                if ($user->centers()->doesntExist()) {
                    $user->tokens()->delete();
                    $user->delete();
                }
            }
        });
    }

    /**
     * Brings back the accounts `suspendAll()` soft-deleted. A deleted account
     * that still holds an assignment was deleted with its center: removing a
     * user drops the assignment first.
     */
    public function reinstateAll(Center $center): void
    {
        DB::transaction(function () use ($center): void {
            foreach ($center->users()->onlyTrashed()->get() as $user) {
                $user->restore();
            }
        });
    }

    /**
     * Drops the user's permissions on the center and revokes their
     * center-scoped tokens. An account left without any center is signed out
     * everywhere and soft-deleted.
     */
    private function detach(Center $center, User $user): void
    {
        DB::transaction(function () use ($center, $user): void {
            $user->syncCenterPermissions($center, []);
            $center->users()->detach($user->id);

            $user->tokens()
                ->whereJsonContains('abilities', CenterAuthService::centerAccessAbilityFor($center->id))
                ->delete();

            if ($user->centers()->doesntExist()) {
                $user->tokens()->delete();
                $user->delete();
            }
        });
    }

    /**
     * Eager-loads the linked trainer on each user's assignment pivot.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function loadTrainers(Collection $users): Collection
    {
        (new Collection($users->pluck('pivot')->all()))->load('trainer');

        return $users;
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotPrimary(User $member): void
    {
        if ($member->pivot?->is_primary) {
            throw ValidationException::withMessages([
                'user' => [__('centers::validation.cannot_modify_primary_user')],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotSelf(User $actor, User $member): void
    {
        if ($actor->is($member)) {
            throw ValidationException::withMessages([
                'user' => [__('centers::validation.cannot_modify_self')],
            ]);
        }
    }

    /**
     * @param  array<string>  $permissions
     *
     * @throws ValidationException When the actor does not hold one of the permissions.
     */
    private function ensureCanGrant(Center $center, User $actor, array $permissions): void
    {
        $errors = collect($permissions)
            ->reject(fn (string $permission): bool => $actor->hasCenterPermission($center, CenterUserPermission::from($permission)))
            ->mapWithKeys(fn (string $permission, int $index): array => [
                "permissions.{$index}" => [__('centers::validation.cannot_grant_permission')],
            ])
            ->all();

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
