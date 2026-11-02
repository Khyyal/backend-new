<?php

namespace Modules\Centers\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Support\Concerns\HasDevices;
use Modules\Support\Enums\ActivationStatus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'phone', 'password', 'phone_verified_at'])]
#[Hidden(['password'])]
#[Table('center_users')]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens,HasDevices, HasFactory, HasRoles, Notifiable;

    use SoftDeletes;

    protected string $guard_name = 'center_user';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function centers(): BelongsToMany
    {
        return $this->belongsToMany(
            Center::class,
            'center_user_assignment',
            'user_id',
            'center_id'
        )
            ->using(CenterUser::class)
            ->withPivot([
                'status',
                'joined_at',
                'is_primary',
                'trainer_id',
            ])
            ->withTimestamps();
    }

    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * The user's assignment pivot for the given center, if any.
     */
    public function centerAssignment(Center $center): ?CenterUser
    {
        /** @var Center|null $assigned */
        $assigned = $this->centers()->whereKey($center->getKey())->first();

        /** @var CenterUser|null */
        return $assigned?->pivot;
    }

    public function isPrimaryOf(Center $center): bool
    {
        return $this->centers()
            ->whereKey($center->getKey())
            ->wherePivot('is_primary', true)
            ->wherePivot('status', ActivationStatus::ACTIVE->value)
            ->exists();
    }

    /**
     * Whether the user holds the permission on the given center. Requires an
     * active assignment; the primary user holds every center permission.
     */
    public function hasCenterPermission(Center $center, CenterUserPermission $permission): bool
    {
        return $this->hasAnyCenterPermission($center, [$permission]);
    }

    /**
     * Whether the user holds at least one of the permissions on the center.
     *
     * @param  array<CenterUserPermission>  $permissions
     */
    public function hasAnyCenterPermission(Center $center, array $permissions): bool
    {
        $granted = $this->grantedCenterPermissions($center);

        if ($granted === null) {
            return true;
        }

        return collect($permissions)->contains(fn (CenterUserPermission $permission): bool => in_array($permission->value, $granted, true));
    }

    /**
     * Whether the user holds every one of the permissions on the center.
     *
     * @param  array<CenterUserPermission>  $permissions
     */
    public function hasAllCenterPermissions(Center $center, array $permissions): bool
    {
        $granted = $this->grantedCenterPermissions($center);

        if ($granted === null) {
            return true;
        }

        return collect($permissions)->every(fn (CenterUserPermission $permission): bool => in_array($permission->value, $granted, true));
    }

    /**
     * Permission names the user holds on the center: `null` means all of them
     * (active primary user), an empty list means none (no active assignment).
     *
     * @return array<string>|null
     */
    private function grantedCenterPermissions(Center $center): ?array
    {
        $assignment = $this->centerAssignment($center);

        if ($assignment === null || $assignment->status !== ActivationStatus::ACTIVE) {
            return [];
        }

        if ($assignment->is_primary) {
            return null;
        }

        return $this->centerPermissionNames($center);
    }

    /**
     * Replaces the user's permissions on the given center.
     *
     * @param  array<CenterUserPermission|string>  $permissions
     */
    public function syncCenterPermissions(Center $center, array $permissions): void
    {
        $names = collect($permissions)
            ->map(fn (CenterUserPermission|string $permission): string => $permission instanceof CenterUserPermission ? $permission->value : $permission)
            ->each(fn (string $name): Permission => Permission::findOrCreate($name, 'center_user'))
            ->all();

        $this->scopePermissionsTo($center);
        $this->syncPermissions($names);
    }

    /**
     * Names of the permissions the user holds on the given center.
     *
     * @return array<string>
     */
    public function centerPermissionNames(Center $center): array
    {
        $this->scopePermissionsTo($center);

        return $this->getDirectPermissions()->pluck('name')->values()->all();
    }

    /**
     * Points the (Octane-shared) permission registrar at the center's team and
     * drops relations loaded for another team.
     */
    private function scopePermissionsTo(Center $center): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($center->getKey());
        $this->unsetRelation('permissions');
        $this->unsetRelation('roles');
    }
}
