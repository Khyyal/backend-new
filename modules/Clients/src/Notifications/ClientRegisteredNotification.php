<?php

namespace Modules\Clients\Notifications;

use Modules\Clients\Models\Client;
use Modules\Support\Notifications\AppNotification;

final class ClientRegisteredNotification extends AppNotification
{
    public function __construct(
        public readonly Client $client,
    ) {}

    public function type(): string
    {
        return 'client_registered';
    }

    protected function translationKey(): string
    {
        return 'clients::notifications.registered';
    }

    public function data(): array
    {
        return [
            'client_id' => $this->client->getKey(),
        ];
    }

    protected function replacements(string $locale): array
    {
        return [
            'phone' => (string) $this->client->phone_number,
        ];
    }
}
