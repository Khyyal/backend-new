<?php

namespace Modules\Centers\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Centers\Http\Resources\Center\UserResource;
use Modules\Support\Concerns\HasDevices;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'phone', 'password','phone_verified_at'])]
#[Hidden(['password'])]
#[Table("center_users")]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens,HasFactory, Notifiable, HasDevices, HasRoles ;
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
            ->withPivot([
                'status',
                'joined_at',
            ])
            ->withTimestamps();
    }



    public function hasVerifiedPhone(): bool
    {
        return !is_null($this->phone_verified_at);
    }
}
