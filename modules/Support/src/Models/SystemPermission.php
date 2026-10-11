<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'description'])]
#[Table('system_permissions')]
class SystemPermission extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            SystemRole::class,
            'system_permission_role',
            'system_permission_id',
            'system_role_id'
        );
    }
}
