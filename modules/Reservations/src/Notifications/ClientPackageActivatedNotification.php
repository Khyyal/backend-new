<?php

namespace Modules\Reservations\Notifications;

use Modules\Reservations\Models\ClientPackage;
use Modules\Support\Notifications\AppNotification;

final class ClientPackageActivatedNotification extends AppNotification
{
    public function __construct(
        public readonly ClientPackage $package,
    ) {}

    public function type(): string
    {
        return 'client_package_activated';
    }

    protected function translationKey(): string
    {
        return 'reservations::notifications.package.activated';
    }

    public function data(): array
    {
        return [
            'client_package_id' => $this->package->getKey(),
            'service_id' => $this->package->service_id,
        ];
    }

    protected function replacements(string $locale): array
    {
        return [
            'service' => (string) $this->package->loadMissing('service')->service?->getTranslation('name', $locale),
            'sessions' => (int) $this->package->total_sessions,
        ];
    }
}
