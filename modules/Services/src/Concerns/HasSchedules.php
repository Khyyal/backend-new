<?php

namespace Modules\Services\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Services\Models\Schedule;

trait HasSchedules
{
    public function schedules(): MorphMany
    {
        return $this->morphMany(Schedule::class, 'schedulable');
    }
}
