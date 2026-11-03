<?php

namespace Modules\Support\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

final class NotificationService implements NotificationServiceContract
{
    public function send(Model|Collection|array $notifiables, AppNotification $notification): void
    {
        Notification::send($notifiables, $notification);
    }
}
