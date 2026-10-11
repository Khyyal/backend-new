<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Support\Concerns\HasSystemRoles;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\ActivationStatus;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
#[Table('system_users')]
class SystemUser extends Authenticatable
{
    /** @use HasFactory<SystemUserFactory> */
    use HasApiTokens, HasFactory, HasSystemRoles, Notifiable, SoftDeletes;

    protected static function newFactory(): SystemUserFactory
    {
        return SystemUserFactory::new();
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
