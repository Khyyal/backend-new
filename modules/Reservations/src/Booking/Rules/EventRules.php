<?php

namespace Modules\Reservations\Booking\Rules;

use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\BookingQuote;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\ReservationRules;
use Modules\Reservations\Enums\ReservationLineKind;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Services\Models\Event;

/**
 * An event reservation buys tickets of exactly one of the event's price
 * options, for a day within the event dates while booking is open.
 */
class EventRules implements ReservationRules
{
    public function __construct(private BookingGuard $guard) {}

    public function quote(BookingRequest $request): BookingQuote
    {
        if ($request->usesPackage()) {
            throw ReservationNotAllowedException::because('package_not_supported');
        }

        if (count($request->lines) !== 1) {
            throw ReservationNotAllowedException::because('single_ticket_option');
        }

        $line = $request->lines[array_key_first($request->lines)];
        $quantity = (int) $line['quantity'];

        $this->guard->ensureBookable(new BookingRequest(
            service: $request->service,
            client: $request->client,
            date: $request->date,
            quantity: $quantity,
        ));

        /** @var Event $event */
        $event = $request->service->serviceable;
        $day = $request->date->startOfDay();

        if (($event->start_date !== null && $day->lt($event->start_date->startOfDay()))
            || ($event->end_date !== null && $day->gt($event->end_date->startOfDay()))) {
            throw ReservationNotAllowedException::because('outside_event_dates');
        }

        $today = now()->startOfDay();

        if (($event->open_date !== null && $today->lt($event->open_date->startOfDay()))
            || ($event->close_date !== null && $today->gt($event->close_date->startOfDay()))) {
            throw ReservationNotAllowedException::because('event_booking_closed');
        }

        $this->guard->ensureNotInPast($request->date);
        $this->guard->ensureNotBlocked($request->service, $request->date);

        $option = $request->service->priceOptions()->whereKey($line['price_option_id'])->first()
            ?? throw ReservationNotAllowedException::because('invalid_price_option');

        $reserved = $this->guard->reservedQuantity($request->service, $request->date, ignoreReservationId: $request->ignoreReservationId);
        $this->guard->ensureCapacity($event->max_tickets_per_day, $reserved, $quantity);

        $unitPrice = (float) $option->price;
        $subtotal = round($unitPrice * $quantity, 2);

        return new BookingQuote(
            date: $request->date,
            quantity: $quantity,
            unitPrice: $unitPrice,
            subtotal: $subtotal,
            priceOptionId: $option->getKey(),
            lines: [[
                'price_option_id' => $option->getKey(),
                'kind' => ReservationLineKind::Ticket,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]],
        );
    }
}
