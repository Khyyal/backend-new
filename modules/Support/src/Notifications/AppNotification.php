<?php

namespace Modules\Support\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Support\Notifications\Channels\LocalizedFcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * Base for every in-app + push notification.
 *
 * The inbox row stores the title and body in every supported locale so the app
 * renders whichever language is active; the push is localized per device.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    public const array LOCALES = ['en', 'ar'];

    /**
     * Inbox / push type the app switches on (e.g. `reservation_confirmed`).
     */
    abstract public function type(): string;

    /**
     * Translation key holding `title` and `body` (e.g. `reservations::notifications.confirmed`).
     */
    abstract protected function translationKey(): string;

    /**
     * Ids the app uses to deep link.
     *
     * @return array<string, int|string|null>
     */
    abstract public function data(): array;

    /**
     * Placeholders for the title and body in the given locale.
     *
     * @return array<string, string|int>
     */
    protected function replacements(string $locale): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', LocalizedFcmChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = [];
        $body = [];

        foreach (self::LOCALES as $locale) {
            $title[$locale] = $this->title($locale);
            $body[$locale] = $this->body($locale);
        }

        return [
            'type' => $this->type(),
            'title' => $title,
            'body' => $body,
            ...$this->data(),
        ];
    }

    public function toFcm(object $notifiable, ?string $locale = null): FcmMessage
    {
        $locale ??= (string) config('app.locale');

        return new FcmMessage(
            data: array_map(
                fn (int|string $value): string => (string) $value,
                array_filter(['type' => $this->type(), ...$this->data()], fn (int|string|null $value): bool => $value !== null),
            ),
            notification: new FcmNotification(
                title: $this->title($locale),
                body: $this->body($locale),
            ),
        );
    }

    public function title(string $locale): string
    {
        return trans($this->translationKey().'.title', $this->replacements($locale), $locale);
    }

    public function body(string $locale): string
    {
        return trans($this->translationKey().'.body', $this->replacements($locale), $locale);
    }
}
