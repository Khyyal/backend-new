<?php

namespace Modules\Centers\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Support\Concerns\HasDevices;

#[Fillable(['name', 'phone', 'password'])]
#[Hidden(['password'])]
#[Table("center_users")]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable , HasDevices;



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
}
