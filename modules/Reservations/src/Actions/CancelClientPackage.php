<?php

namespace Modules\Reservations\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ClientPackageCancelled;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;

/**
 * Cancels a client package together with the sessions still to come. Center
 * staff may only cancel a package no money is held for; a paid one is
 * cancelled by refunding its purchase (`RefundPurchase`).
 */
class CancelClientPackage
{
    public function __construct(
        private ReservationStateService $reservationState,
        private PurchaseStateService $purchaseState,
        private PaymentStateService $paymentState,
    ) {}

    /**
     * Cancels an unpaid package of its own (not part of a membership) and
     * closes the purchase that sold nothing else.
     *
     * @throws ReservationNotAllowedException
     */
    public function execute(ClientPackage $clientPackage, ?Model $cancelledBy = null): ClientPackage
    {
        return DB::transaction(function () use ($clientPackage, $cancelledBy): ClientPackage {
            $package = $this->lock($clientPackage);

            if (in_array($package->status, [ClientPackageStatus::Pending, ClientPackageStatus::Cancelled], true)) {
                throw ReservationNotAllowedException::because('package_not_cancellable');
            }

            if ($package->client_membership_id !== null) {
                throw ReservationNotAllowedException::because('package_membership_bound');
            }

            /** @var Purchase|null $purchase */
            $purchase = $package->purchaseItems()->with('purchase.payments')->latest('id')->first()?->purchase;

            if ($purchase !== null && $this->holdsMoney($purchase)) {
                throw ReservationNotAllowedException::because('package_has_payment');
            }

            $package = $this->void($package, $cancelledBy);

            if ($purchase !== null) {
                $this->closePurchase($purchase);
            }

            return $package;
        });
    }

    /**
     * Cancels the package whatever was paid for it; the caller settles the
     * money. Sessions that already started are kept. Does nothing when the
     * package is already cancelled.
     *
     * @throws ReservationNotAllowedException
     */
    public function void(ClientPackage $clientPackage, ?Model $cancelledBy = null): ClientPackage
    {
        return DB::transaction(function () use ($clientPackage, $cancelledBy): ClientPackage {
            $package = $this->lock($clientPackage);

            if ($package->status === ClientPackageStatus::Cancelled) {
                return $package;
            }

            $package->reservations()
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                ->get()
                ->filter(fn (Reservation $reservation): bool => $reservation->startsAt()->isFuture())
                ->each(fn (Reservation $reservation) => $this->reservationState->cancel($reservation, $cancelledBy, 'package_cancelled'));

            $package = $this->lock($package);
            $package->status = ClientPackageStatus::Cancelled;
            $package->save();

            DB::afterCommit(static function () use ($package): void {
                event(new ClientPackageCancelled($package));
            });

            return $package;
        });
    }

    private function lock(ClientPackage $package): ClientPackage
    {
        return ClientPackage::query()->whereKey($package->getKey())->lockForUpdate()->firstOrFail();
    }

    private function holdsMoney(Purchase $purchase): bool
    {
        return $purchase->payments->contains(
            fn (Payment $payment): bool => $payment->status === PaymentStatus::Processing
                || ($payment->status === PaymentStatus::Succeeded && $payment->refundableAmount() > 0)
        );
    }

    /**
     * A confirmed purchase that only sold this package is cancelled with its
     * open payments, so the center stops owing its fee.
     */
    private function closePurchase(Purchase $purchase): void
    {
        if ($purchase->status !== PurchaseStatus::Confirmed || $purchase->items()->count() !== 1) {
            return;
        }

        $purchase->payments
            ->where('status', PaymentStatus::Pending)
            ->each(fn (Payment $payment) => $this->paymentState->cancel($payment));

        $this->purchaseState->cancel($purchase);
    }
}
