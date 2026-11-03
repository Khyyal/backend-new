<?php

namespace Modules\Centers\Notifications;

use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Notifications\AppNotification;

final class CenterRegisteredNotification extends AppNotification
{
    public function __construct(
        public readonly Center $center,
        public readonly User $owner,
    ) {}

    public function type(): string
    {
        return 'center_registered';
    }

    protected function translationKey(): string
    {
        return 'centers::notifications.registered';
    }

    public function data(): array
    {
        return [
            'center_id' => $this->center->getKey(),
            'user_id' => $this->owner->getKey(),
        ];
    }

    protected function replacements(string $locale): array
    {
        return [
            'center' => (string) $this->center->name,
            'owner' => (string) $this->owner->name,
        ];
    }
}
