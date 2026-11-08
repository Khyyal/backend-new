<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Support\Concerns\HasDevices;
use Modules\Support\Concerns\HasNotificationInbox;
use Modules\Support\Concerns\HasSystemRoles;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemRole as SystemRoleEnum;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
#[Table('system_users')]
class SystemUser extends Authenticatable
{
    /** @use HasFactory<SystemUserFactory> */
    use HasApiTokens, HasDevices, HasFactory, HasNotificationInbox, HasSystemRoles, LogsActivity, SoftDeletes;

    protected static function newFactory(): SystemUserFactory
    {
        return SystemUserFactory::new();
    }

    /**
     * Active admins, who receive platform notifications (new clients, new centers).
     *
     * @return Collection<int, self>
     */
    public static function notifiableAdmins(): Collection
    {
        return self::query()->where('status', ActivationStatus::ACTIVE)->get();
    }

    /**
     * Active super admins other than the given user.
     */
    public static function otherActiveSuperAdminsExist(self $except): bool
    {
        return self::query()
            ->whereKeyNot($except->getKey())
            ->where('status', ActivationStatus::ACTIVE)
            ->whereHas('roles', fn ($query) => $query->where('name', SystemRoleEnum::SuperAdmin->value))
            ->exists();
    }

    /**
     * The password is never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('system_user');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => ActivationStatus::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === ActivationStatus::ACTIVE;
    }
}
