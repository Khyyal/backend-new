<?php

namespace Modules\Reservations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\CheckoutResult;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Services\ReservationStateService;

/**
 * Puts pending reservations of one client at one center into a purchase.
 *
 * Online: reservations stay pending until the purchase is confirmed.
 * Cash on arrival: reservations are confirmed now, the payment stays pending.
 * Manual (center staff only): paid at the desk, everything is confirmed now.
 */
class CheckoutReservations
{
    public function __construct(
        private StartCheckout $startCheckout,
        private BookingGuard $guard,
        private ReservationStateService $reservationState,
    ) {}

    /**
     * @param  array<int, Reservation>  $reservations
     *
     * @throws ReservationNotAllowedException
     */
    public function execute(
        Client $client,
        array $reservations,
        PurchaseSource $source,
        PaymentMethod $method,
        ?string $paymentCompany = null,
    ): CheckoutResult {
        $reservations = array_values($reservations);
        $this->ensureCheckoutable($client, $reservations);

        if ($method === PaymentMethod::CashOnArrival) {
            foreach ($reservations as $reservation) {
                $this->guard->ensureSettingEnabled($reservation->service, 'enable_cash_on_arrival', 'cash_on_arrival_disabled');
            }
        }

        return DB::transaction(function () use ($client, $reservations, $source, $method, $paymentCompany): CheckoutResult {
            $result = $this->startCheckout->execute(
                buyer: $client,
                merchant: $reservations[0]->center,
                source: $source,
                method: $method,
                items: array_map(fn (Reservation $reservation): array => [
                    'purchasable' => $reservation,
                    'name' => $reservation->service->name,
                    'unit_price' => (float) $reservation->total_amount,
                    'metadata' => ['quantity' => $reservation->quantity],
                ], $reservations),
                paymentCompany: $paymentCompany,
            );

            if ($method === PaymentMethod::CashOnArrival) {
                foreach ($reservations as $reservation) {
                    $this->reservationState->confirm($reservation);
                }
            }

            return $result;
        });
    }

    /**
     * @param  array<int, Reservation>  $reservations
     *
     * @throws ReservationNotAllowedException
     */
    private function ensureCheckoutable(Client $client, array $reservations): void
    {
        if ($reservations === []) {
            throw ReservationNotAllowedException::because('checkout_mismatch');
        }

        $centerId = $reservations[array_key_first($reservations)]->center_id;

        foreach ($reservations as $reservation) {
            $isCheckoutable = $reservation->status === ReservationStatus::Pending
                && $reservation->client_package_id === null
                && (float) $reservation->total_amount > 0
                && (int) $reservation->client_id === (int) $client->getKey()
                && (int) $reservation->center_id === (int) $centerId
                && ! $reservation->purchaseItems()->exists();

            if (! $isCheckoutable) {
                throw ReservationNotAllowedException::because('checkout_mismatch');
            }
        }

    }
}
