<?php

namespace Modules\Reservations\Actions;

use Modules\Clients\Models\Client;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\CheckoutPricer;
use Modules\Reservations\Booking\CheckoutQuote;
use Modules\Reservations\Booking\ReservationRulesResolver;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Services\Models\ServicePackage;

/**
 * Prices a booking or a package purchase with the running promotions,
 * without booking or reserving anything.
 */
class QuoteCheckout
{
    public function __construct(
        private ReservationRulesResolver $rules,
        private CheckoutPricer $pricer,
    ) {}

    /**
     * @throws ReservationNotAllowedException when the slot cannot be booked
     */
    public function forBooking(BookingRequest $request, ?string $couponCode = null): CheckoutQuote
    {
        $booking = $this->rules->quote($request);

        return $this->pricer->quote($request->service->center, $request->client, [[
            'service_id' => $request->service->getKey(),
            'unit_price' => $booking->subtotal,
        ]], $couponCode);
    }

    public function forPackage(Client $client, ServicePackage $servicePackage, ?string $couponCode = null): CheckoutQuote
    {
        return $this->pricer->quote($servicePackage->service->center, $client, [[
            'service_id' => $servicePackage->service_id,
            'unit_price' => $servicePackage->price,
        ]], $couponCode);
    }
}
