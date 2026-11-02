<?php

namespace Modules\Centers\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Services\Models\Trainer;
use Modules\Support\Enums\ActivationStatus;

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

    /**
     * The center trainer this user is linked to (null for the primary user).
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class)->withTrashed();
    }
}
