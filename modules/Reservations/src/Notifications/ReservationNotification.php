<?php

namespace Modules\Reservations\Notifications;

use Modules\Reservations\Models\Reservation;
use Modules\Support\Notifications\AppNotification;

/**
 * A reservation lifecycle update; `$event` picks the copy (created, confirmed, cancelled, ...).
 */
final class ReservationNotification extends AppNotification
{
    public function __construct(
        public readonly Reservation $reservation,
        public readonly string $event,
    ) {}

    public function type(): string
    {
        return "reservation_{$this->event}";
    }

    protected function translationKey(): string
    {
        return "reservations::notifications.reservation.{$this->event}";
    }

    public function data(): array
    {
        return [
            'reservation_id' => $this->reservation->getKey(),
            'center_id' => $this->reservation->center_id,
            'status' => $this->reservation->status->value,
        ];
    }

    protected function replacements(string $locale): array
    {
        $reservation = $this->reservation->loadMissing(['service', 'center', 'client']);

        return [
            'service' => (string) $reservation->service?->getTranslation('name', $locale),
            'center' => (string) $reservation->center?->name,
            'client' => (string) $reservation->client?->full_name,
            'date' => (string) $reservation->date?->format('Y-m-d'),
            'time' => (string) ($reservation->start_time ? substr((string) $reservation->start_time, 0, 5) : ''),
        ];
    }
}
