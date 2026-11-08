<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Support\Database\Factories\SystemRoleFactory;
use Modules\Support\Enums\SystemPermission as SystemPermissionEnum;
use Modules\Support\Enums\SystemRole as SystemRoleEnum;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'description'])]
#[Table('system_roles')]
class SystemRole extends Model
{
    /** @use HasFactory<SystemRoleFactory> */
    use HasFactory, LogsActivity;

    protected static function newFactory(): SystemRoleFactory
    {
        return SystemRoleFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('system_role');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            SystemPermission::class,
            'system_permission_role',
            'system_role_id',
            'system_permission_id'
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            SystemUser::class,
            'system_role_user',
            'system_role_id',
            'system_user_id'
        );
    }

    public function isProtected(): bool
    {
        return SystemRoleEnum::isProtected($this->name);
    }

    public function hasPermission(SystemPermissionEnum|string $permission): bool
    {
        $name = $permission instanceof SystemPermissionEnum ? $permission->value : $permission;

        return $this->permissions->contains('name', $name);
    }

    /**
     * @param  array<int, SystemPermissionEnum|string>  $permissions
     */
    public function givePermissionTo(array $permissions): static
    {
        $names = array_map(
            fn (SystemPermissionEnum|string $permission): string => $permission instanceof SystemPermissionEnum
                ? $permission->value
                : $permission,
            $permissions
        );

        $ids = SystemPermission::whereIn('name', $names)->pluck('id');

        $this->permissions()->syncWithoutDetaching($ids);
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * Replace the role's permissions with exactly the given ones.
     *
     * @param  array<int, SystemPermissionEnum|string>  $permissions
     */
    public function syncPermissions(array $permissions): static
    {
        $names = array_map(
            fn (SystemPermissionEnum|string $permission): string => $permission instanceof SystemPermissionEnum
                ? $permission->value
                : $permission,
            $permissions
        );

        $this->permissions()->sync(SystemPermission::whereIn('name', $names)->pluck('id'));
        $this->unsetRelation('permissions');

        return $this;
    }
}
