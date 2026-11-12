<?php

namespace Modules\Reservations\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;
use Throwable;

/**
 * Gives a confirmed purchase's money back in full and cancels the purchase
 * with everything it sold: open reservations, packages and memberships (with
 * their upcoming sessions). Online payments go back through their gateway; a
 * manual payment is only recorded, the center hands the money back itself.
 * When a gateway refuses, nothing changes.
 */
class RefundPurchase
{
    public function __construct(
        private PurchaseStateService $purchaseState,
        private PaymentStateService $paymentState,
        private PaymentGatewayManager $gateways,
        private ReservationStateService $reservationState,
        private CancelClientPackage $cancelClientPackage,
    ) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(Purchase $purchase, ?Model $refundedBy = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $refundedBy): Purchase {
            $locked = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseStatus::Confirmed) {
                throw ReservationNotAllowedException::because('purchase_not_refundable');
            }

            /** @var Collection<int, Payment> $payments */
            $payments = $locked->payments()
                ->where('status', PaymentStatus::Succeeded)
                ->get()
                ->filter(fn (Payment $payment): bool => $payment->refundableAmount() > 0);

            if ($payments->isEmpty()) {
                throw ReservationNotAllowedException::because('nothing_to_refund');
            }

            // Cancelled first: a reservation cancel only refunds its own share of a confirmed purchase.
            $locked = $this->purchaseState->cancel($locked);

            $this->cancelSold($locked, $refundedBy);

            // The gateway goes last, so a failure before it never leaves money moved.
            $payments->each(fn (Payment $payment) => $this->refund($payment));

            return $locked;
        });
    }

    private function cancelSold(Purchase $purchase, ?Model $cancelledBy): void
    {
        $items = $purchase->items()
            ->whereIn('purchasable_type', [
                (new Reservation)->getMorphClass(),
                (new ClientPackage)->getMorphClass(),
                (new ClientMembership)->getMorphClass(),
            ])
            ->with('purchasable')
            ->get();

        foreach ($items as $item) {
            $purchasable = $item->purchasable;

            if ($purchasable instanceof Reservation) {
                $this->cancelReservation($purchasable, $cancelledBy);
            }

            if ($purchasable instanceof ClientPackage) {
                $this->cancelClientPackage->void($purchasable, $cancelledBy);
            }

            if ($purchasable instanceof ClientMembership) {
                $this->cancelMembership($purchasable, $cancelledBy);
            }
        }
    }

    private function cancelReservation(Reservation $reservation, ?Model $cancelledBy): void
    {
        if (in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            $this->reservationState->cancel($reservation, $cancelledBy, 'purchase_refunded');
        }
    }

    private function cancelMembership(ClientMembership $membership, ?Model $cancelledBy): void
    {
        if ($membership->status === ClientMembershipStatus::Cancelled) {
            return;
        }

        $membership->reservations()
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->get()
            ->filter(fn (Reservation $reservation): bool => $reservation->startsAt()->isFuture())
            ->each(fn (Reservation $reservation) => $this->cancelReservation($reservation, $cancelledBy));

        $membership->packages()->get()
            ->each(fn (ClientPackage $package) => $this->cancelClientPackage->void($package, $cancelledBy));

        $membership->update(['status' => ClientMembershipStatus::Cancelled]);
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function refund(Payment $payment): void
    {
        $amount = $payment->refundableAmount();

        if ($payment->method === PaymentMethod::Online) {
            try {
                $refunded = $this->gateways->driver($payment->payment_company ?: null)->refund($payment, $amount);
            } catch (Throwable $exception) {
                throw ReservationNotAllowedException::because('purchase_refund_failed', previous: $exception);
            }

            if (! $refunded) {
                throw ReservationNotAllowedException::because('purchase_refund_failed');
            }
        }

        $this->paymentState->recordRefund($payment, $amount);
    }
}
