<?php

namespace Modules\Settlements\Notifications;

use Modules\Settlements\Models\CenterPayout;
use Modules\Support\Notifications\AppNotification;

/**
 * Tells the center's primary user that a payout was transferred.
 */
final class CenterPayoutPaidNotification extends AppNotification
{
    public function __construct(
        public readonly CenterPayout $payout,
    ) {}

    public function type(): string
    {
        return 'center_payout_paid';
    }

    protected function translationKey(): string
    {
        return 'settlements::notifications.payout_paid';
    }

    public function data(): array
    {
        return [
            'payout_id' => $this->payout->getKey(),
            'center_id' => $this->payout->center_id,
        ];
    }

    protected function replacements(string $locale): array
    {
        return [
            'amount' => number_format($this->payout->amount / 100, 2),
            'reference' => (string) $this->payout->reference,
        ];
    }
}
