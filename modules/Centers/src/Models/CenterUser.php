<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Centers\Enums\ActivationStatus;

class CenterUser extends Pivot
{
    protected $table = 'center_user_assignment';

    protected function casts(): array
    {
        return [
            'status' => ActivationStatus::class,
            'joined_at' => 'date',
            'is_primary' => 'boolean',
        ];
    }
}
