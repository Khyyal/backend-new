<?php

namespace Modules\Clients\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Clients\Events\ClientRegistered;
use Modules\Clients\Notifications\ClientRegisteredNotification;
use Modules\Support\Models\SystemUser;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

final class SendClientRegisteredNotification implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationServiceContract $notifications,
    ) {}

    public function handle(ClientRegistered $event): void
    {
        $admins = SystemUser::notifiableAdmins();

        if ($admins->isNotEmpty()) {
            $this->notifications->send($admins, new ClientRegisteredNotification($event->client));
        }
    }
}
