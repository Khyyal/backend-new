<?php

namespace Modules\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Centers\Events\CenterCreated;
use Modules\Notifications\Senders\CenterWhatsAppNotifier;

final class SendCenterWelcomeWhatsApp implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly CenterWhatsAppNotifier $notifier,
    ) {}

    public function handle(CenterCreated $event): void
    {
        $this->notifier->send(
            $event->center,
            (string) config('notifications.whatsapp.center_welcome_template'),
            [(string) $event->center->name],
        );
    }
}
