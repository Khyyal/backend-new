<?php

namespace Modules\Purchase\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Modules\Centers\Models\Center;
use Modules\Purchase\Events\PaymentFailed;
use Modules\Purchase\Events\PaymentRefunded;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Notifications\PurchaseNotification;
use Modules\Support\Notifications\Contracts\NotificationServiceContract;

/**
 * Notifies the buyer. `PaymentSucceeded` is not notified on its own because it
 * confirms the purchase right away, which sends `purchase_confirmed`.
 */
final class SendPurchaseNotifications implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationServiceContract $notifications,
    ) {}

    public function handleConfirmed(PurchaseConfirmed $event): void
    {
        $this->notifyBuyer($event->purchase, 'confirmed');
    }

    public function handleCancelled(PurchaseCancelled $event): void
    {
        $this->notifyBuyer($event->purchase, 'cancelled');
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->notifyBuyer($event->payment->purchase, 'payment_failed', (float) $event->payment->amount);
    }

    public function handlePaymentRefunded(PaymentRefunded $event): void
    {
        $this->notifyBuyer($event->payment->purchase, 'payment_refunded', $event->amount);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PurchaseConfirmed::class => 'handleConfirmed',
            PurchaseCancelled::class => 'handleCancelled',
            PaymentFailed::class => 'handlePaymentFailed',
            PaymentRefunded::class => 'handlePaymentRefunded',
        ];
    }

    private function notifyBuyer(?Purchase $purchase, string $event, ?float $amount = null): void
    {
        $recipients = $this->recipients($purchase?->buyer);

        if ($purchase === null || $recipients->isEmpty()) {
            return;
        }

        $this->notifications->send($recipients, new PurchaseNotification($purchase, $event, $amount));
    }

    /**
     * A center buyer is notified through its active staff.
     *
     * @return Collection<int, Model>
     */
    private function recipients(?Model $buyer): Collection
    {
        return match (true) {
            $buyer instanceof Center => $buyer->notifiableUsers(),
            $buyer !== null && method_exists($buyer, 'notify') => new Collection([$buyer]),
            default => new Collection,
        };
    }
}
