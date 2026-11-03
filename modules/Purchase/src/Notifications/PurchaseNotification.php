<?php

namespace Modules\Purchase\Notifications;

use Modules\Purchase\Models\Purchase;
use Modules\Support\Notifications\AppNotification;

/**
 * A purchase or payment update for the buyer; `$event` picks the copy.
 */
final class PurchaseNotification extends AppNotification
{
    public function __construct(
        public readonly Purchase $purchase,
        public readonly string $event,
        public readonly ?int $amount = null,
    ) {}

    public function type(): string
    {
        return "purchase_{$this->event}";
    }

    protected function translationKey(): string
    {
        return "purchase::notifications.{$this->event}";
    }

    public function data(): array
    {
        return [
            'purchase_id' => $this->purchase->getKey(),
            'status' => $this->purchase->status->value,
        ];
    }

    protected function replacements(string $locale): array
    {
        return [
            'id' => (string) $this->purchase->getKey(),
            'amount' => number_format(($this->amount ?? $this->purchase->total_amount) / 100, 2),
        ];
    }
}
