<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Support\Models\ActionLog;

trait ActionActor
{
    /**
     * Get all actions performed by this model.
     */
    public function actionLogs(): MorphMany
    {
        return $this->morphMany(ActionLog::class, 'actor');
    }

    /**
     * Record an action performed by this model on another model.
     */
    public function performAction(
        Model $actionable,
        string $action,
    ): ActionLog {
        return $actionable->actionLogs()->create([
            'action' => $action,
            'actor_id' => $this->getKey(),
            'actor_type' => $this->getMorphClass(),
        ]);
    }

    /**
     * Check whether this actor has performed a specific action.
     */
    public function hasPerformedAction(string $action): bool
    {
        return $this->actionLogs()
            ->where('action', $action)
            ->exists();
    }

    /**
     * Get the latest action performed by this actor.
     */
    public function latestPerformedAction(): ?ActionLog
    {
        return $this->actionLogs()
            ->latest()
            ->first();
    }

    /**
     * Get the latest action of a specific type performed by this actor.
     */
    public function latestPerformedActionOf(string $action): ?ActionLog
    {
        return $this->actionLogs()
            ->where('action', $action)
            ->latest()
            ->first();
    }
}
