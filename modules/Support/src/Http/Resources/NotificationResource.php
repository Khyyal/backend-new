<?php

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Models\Notification;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'string', 'Notification UUID.', example: '0f8b7f2e-6a3c-4d51-9a2e-4b7c1d9e3f60')]
    #[ResponseField('type', 'string', 'Notification type key (e.g. `reservation.confirmed`); drives the app deep link.', nullable: true, example: 'reservation.confirmed')]
    #[ResponseField('title', 'string', 'Title in the `Accept-Language` locale (falls back to the default locale).', nullable: true, example: 'Booking confirmed')]
    #[ResponseField('body', 'string', 'Body in the `Accept-Language` locale.', nullable: true, example: 'Your ride on 5 June at 16:00 is confirmed.')]
    #[ResponseField('payload', 'object', 'Raw stored data: `type`, `title`/`body` in every locale, and deep-link IDs (e.g. `reservation_id`).', example: ['type' => 'reservation.confirmed', 'title' => ['en' => 'Booking confirmed', 'ar' => 'تم تأكيد الحجز'], 'body' => ['en' => 'Your ride on 5 June at 16:00 is confirmed.', 'ar' => 'تم تأكيد ركوبك يوم 5 يونيو الساعة 16:00.'], 'reservation_id' => 311])]
    #[ResponseField('read_at', 'string', 'When it was read (ISO-8601); null while unread.', nullable: true, example: 'No-example')]
    #[ResponseField('created_at', 'string', 'When it was sent (ISO-8601).', example: '2026-06-01T08:00:00.000000Z')]
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $fallback = (string) config('app.fallback_locale');

        return [
            'id' => $this->id,
            /** @var string */
            'type' => $this->data['type'] ?? null,
            /** @var string|null Title in the request locale. */
            'title' => $this->localized('title', $locale, $fallback),
            /** @var string|null Body in the request locale. */
            'body' => $this->localized('body', $locale, $fallback),
            /** @var array<string, mixed> Raw payload: every locale plus deep-link ids. */
            'payload' => $this->data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    private function localized(string $key, string $locale, string $fallback): ?string
    {
        $value = $this->data[$key] ?? null;

        if (! is_array($value)) {
            return $value;
        }

        return $value[$locale] ?? $value[$fallback] ?? reset($value) ?: null;
    }
}
