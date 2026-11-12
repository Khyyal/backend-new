<?php

namespace Modules\Reservations\Notifications;

use Modules\Reservations\Models\ClientPackage;
use Modules\Support\Notifications\AppNotification;

/**
 * A client package ending update; `$event` picks the copy (expiring, expired, exhausted, cancelled).
 */
final class ClientPackageNotification extends AppNotification
{
    public function __construct(
        public readonly ClientPackage $package,
        public readonly string $event,
    ) {}

    public function type(): string
    {
        return "client_package_{$this->event}";
    }

    protected function translationKey(): string
    {
        return "reservations::notifications.package.{$this->event}";
    }

    public function data(): array
    {
        return [
            'client_package_id' => $this->package->getKey(),
            'service_id' => $this->package->service_id,
            'status' => $this->package->status->value,
        ];
    }

    protected function replacements(string $locale): array
    {
        $package = $this->package->loadMissing('service');

        return [
            'service' => (string) $package->service?->getTranslation('name', $locale),
            'date' => (string) $package->expires_at?->format('Y-m-d'),
            'sessions' => (int) $package->total_sessions,
        ];
    }
}
