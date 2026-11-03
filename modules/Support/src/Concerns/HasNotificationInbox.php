<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;
use Modules\Support\Models\Notification;

/**
 * Notifiable models whose inbox uses the Support notification model.
 */
trait HasNotificationInbox
{
    use Notifiable;

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }
}
