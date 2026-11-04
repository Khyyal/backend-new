<?php

namespace Modules\Purchase\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Purchase\Events\CenterPayoutPaid;
use Modules\Purchase\Notifications\CenterPayoutPaidNotification;
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
