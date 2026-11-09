<?php

namespace Modules\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Centers\Models\Center;
use Modules\Notifications\Senders\CenterWhatsAppNotifier;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Purchase\Models\Purchase;

/**
 * Tells a center it received a purchase after a quiet spell: no earlier
 * confirmed or completed purchase within the quiet window (a first purchase
 * counts). Only earlier purchases are compared, so two confirmed together
 * cannot silence each other.
 */
final class SendCenterPurchaseWhatsApp implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly CenterWhatsAppNotifier $notifier,
    ) {}

    public function handle(PurchaseConfirmed $event): void
    {
        $purchase = $event->purchase;

        // Checked by type first: other merchants (e.g. the platform) are not models to load.
        if ($purchase->merchant_type !== (new Center)->getMorphClass() || $this->hadRecentPurchase($purchase)) {
            return;
        }

        $center = Center::query()->find($purchase->merchant_id);

        if ($center === null) {
            return;
        }

        $this->notifier->send(
            $center,
            (string) config('notifications.whatsapp.center_purchase_template'),
            [(string) $center->name],
        );
    }

    private function hadRecentPurchase(Purchase $purchase): bool
    {
        $quietDays = (int) config('notifications.whatsapp.center_purchase_quiet_days');

        return Purchase::query()
            ->where('merchant_type', $purchase->merchant_type)
            ->where('merchant_id', $purchase->merchant_id)
            ->where('id', '<', $purchase->getKey())
            ->whereIn('status', [PurchaseStatus::Confirmed, PurchaseStatus::Completed])
            ->where('created_at', '>=', $purchase->created_at->copy()->subDays($quietDays))
            ->exists();
    }
}
