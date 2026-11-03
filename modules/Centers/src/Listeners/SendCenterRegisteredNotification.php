<?php

namespace Modules\Centers\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Centers\Events\CenterRegistered;
use Modules\Centers\Notifications\CenterRegisteredNotification;
use Modules\Support\Models\SystemUser;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

final class SendCenterRegisteredNotification implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationServiceContract $notifications,
    ) {}

    public function handle(CenterRegistered $event): void
    {
        $admins = SystemUser::notifiableAdmins();

        if ($admins->isNotEmpty()) {
            $this->notifications->send($admins, new CenterRegisteredNotification($event->center, $event->owner));
        }
    }
}
