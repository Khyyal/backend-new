<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Modules\Support\Models\ActionLog;

trait Actionable
{
    /**
     * Get all action logs for this model.
     */
    public function actionLogs(): MorphMany
    {
        return $this->morphMany(ActionLog::class, 'actionable');
    }

    /**
     * Record an action performed on this model.
     */
    public function recordAction(
        string $action,
        ?Model $actor = null,
    ): ActionLog {
        return $this->actionLogs()->create([
            'action' => $action,
            'actor_id' => $actor?->getKey(),
            'actor_type' => $actor?->getMorphClass(),
        ]);
    }

    /**
     * Check whether this model has a specific action.
     */
    public function hasAction(string $action): bool
    {
        return $this->actionLogs()
            ->where('action', $action)
            ->exists();
    }

    /**
     * Get the latest action performed on this model.
     */
    public function latestAction(): ?ActionLog
    {
        return $this->actionLogs()
            ->latest()
            ->first();
    }

    /**
     * Get the latest action of a specific type.
     */
    public function latestActionOf(string $action): ?ActionLog
    {
        return $this->actionLogs()
            ->where('action', $action)
            ->latest()
            ->first();
    }
}
