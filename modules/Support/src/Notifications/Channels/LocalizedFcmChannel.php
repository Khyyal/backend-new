<?php

namespace Modules\Support\Notifications\Channels;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Modules\Support\Notifications\AppNotification;
use NotificationChannels\Fcm\FcmChannel;

/**
 * FCM channel that sends one message per device locale.
 *
 * Firebase is only resolved when there is a token to send to, so users without
 * devices (and environments without credentials) never touch it.
 */
class LocalizedFcmChannel extends FcmChannel
{
    public function __construct(Dispatcher $events, private readonly Container $container)
    {
        $this->events = $events;
    }

    public function send(mixed $notifiable, Notification $notification): ?Collection
    {
        if (! $notification instanceof AppNotification || ! method_exists($notifiable, 'fcmTokensByLocale')) {
            return null;
        }

        /** @var array<string, list<string>> $tokensByLocale */
        $tokensByLocale = $notifiable->fcmTokensByLocale();

        if ($tokensByLocale === []) {
            return null;
        }

        $reports = new Collection;

        foreach ($tokensByLocale as $locale => $tokens) {
            $message = $notification->toFcm($notifiable, $locale);
            $client = $message->client ?? $this->messaging();

            foreach (array_chunk($tokens, self::TOKENS_PER_REQUEST) as $chunk) {
                /** @var MulticastSendReport $report */
                $report = $client->sendMulticast($message, $chunk);
                $reports->push($this->checkReportForFailures($notifiable, $notification, $report));
            }
        }

        return $reports;
    }

    private function messaging(): Messaging
    {
        return $this->client ??= $this->container->make(Messaging::class);
    }
}
