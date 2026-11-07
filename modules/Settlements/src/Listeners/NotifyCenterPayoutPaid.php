<?php

namespace Modules\Settlements\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Settlements\Events\CenterPayoutPaid;
use Modules\Settlements\Notifications\CenterPayoutPaidNotification;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

class NotifyCenterPayoutPaid implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationServiceContract $notifications,
    ) {}

    public function handle(CenterPayoutPaid $event): void
    {
        $primaryUser = $event->payout->center?->primaryUser;

        if ($primaryUser !== null) {
            $this->notifications->send($primaryUser, new CenterPayoutPaidNotification($event->payout));
        }
    }
}
