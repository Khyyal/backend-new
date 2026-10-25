<?php

namespace Modules\Reservations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\CheckoutResult;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;

/**
 * Books a single reservation and checks it out in one transaction, so a
 * failed checkout leaves no reservation holding places. A free reservation
 * has nothing to pay and is confirmed without a purchase.
 */
class BookAndCheckout
{
    public function __construct(
        private CreateReservation $createReservation,
        private CheckoutReservations $checkoutReservations,
        private ReservationStateService $reservationState,
    ) {}

    /**
     * @return array{0: Reservation, 1: CheckoutResult|null}
     *
     * @throws ReservationNotAllowedException
     */
    public function execute(
        BookingRequest $request,
        ReservationSource $source,
        PaymentMethod $method,
        ?string $paymentCompany = null,
        ?string $notes = null,
    ): array {
        [$reservation, $checkout] = DB::transaction(function () use ($request, $source, $method, $paymentCompany, $notes): array {
            $reservation = $this->createReservation->execute($request, $source, notes: $notes);

            if ((float) $reservation->total_amount <= 0) {
                return [$this->reservationState->confirm($reservation), null];
            }

            $checkout = $this->checkoutReservations->execute(
                $request->client,
                [$reservation],
                $source === ReservationSource::Center ? PurchaseSource::Center : PurchaseSource::Client,
                $method,
                $paymentCompany,
            );

            return [$reservation, $checkout];
        });

        // Purchase listeners run after commit and may have confirmed the reservation.
        return [$reservation->fresh(['lines']), $checkout];
    }
}
