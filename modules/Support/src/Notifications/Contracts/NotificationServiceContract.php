<?php

namespace Modules\Support\Notifications\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Support\Notifications\AppNotification;

interface NotificationServiceContract
{
    /**
     * @param  Model|Collection<int, Model>|array<int, Model>  $notifiables
     */
    public function send(Model|Collection|array $notifiables, AppNotification $notification): void;
}
