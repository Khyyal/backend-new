<?php

namespace Modules\Support\Listeners;

use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Messaging\SendReport;
use Modules\Support\Models\Device;
use Modules\Support\Notifications\Channels\LocalizedFcmChannel;
use NotificationChannels\Fcm\FcmChannel;

final class DeleteExpiredNotificationTokens
{
    /**
     * Clear FCM tokens that Firebase reports as invalid or unregistered.
     */
    public function handle(NotificationFailed $event): void
    {
        if (! in_array($event->channel, [FcmChannel::class, LocalizedFcmChannel::class], true)) {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof SendReport || (! $report->messageTargetWasInvalid() && ! $report->messageWasSentToUnknownToken())) {
            return;
        }

        Device::query()
            ->where('fcm_token', $report->target()->value())
            ->update(['fcm_token' => null]);
    }
}
