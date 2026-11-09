<?php

namespace Modules\Reservations\Notifications;

use Modules\Reservations\Models\ClientMembership;
use Modules\Support\Notifications\AppNotification;

/**
 * A client membership ending update; `$event` picks the copy (expiring, expired).
 */
final class ClientMembershipNotification extends AppNotification
{
    public function __construct(
        public readonly ClientMembership $membership,
        public readonly string $event,
    ) {}

    public function type(): string
    {
        return "client_membership_{$this->event}";
    }

    protected function translationKey(): string
    {
        return "reservations::notifications.membership.{$this->event}";
    }

    public function data(): array
    {
        return [
            'client_membership_id' => $this->membership->getKey(),
            'service_id' => $this->membership->service_id,
            'status' => $this->membership->status->value,
        ];
    }

    protected function replacements(string $locale): array
    {
        $membership = $this->membership->loadMissing('service');

        return [
            'membership' => (string) $membership->service?->getTranslation('name', $locale),
            'date' => (string) $membership->expires_at?->format('Y-m-d'),
        ];
    }
}
