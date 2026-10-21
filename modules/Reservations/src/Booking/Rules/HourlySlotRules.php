<?php

namespace Modules\Reservations\Booking\Rules;

use Illuminate\Database\Eloquent\Model;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\BookingQuote;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\ReservationRules;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Models\PriceOption;

/**
 * Rules for services booked by the hour on a duration price option, alone or
 * with a package credit. The slot lasts the option's minutes and must fit a
 * schedule window of the schedule owner (the service itself or a trainer).
 */
abstract class HourlySlotRules implements ReservationRules
{
    public function __construct(protected BookingGuard $guard) {}

    /**
     * The model holding the `schedules` and `max_tickets_per_hour` for this booking.
     *
     * @throws ReservationNotAllowedException
     */
    abstract protected function scheduleOwner(BookingRequest $request): Model;

    abstract protected function trainerId(Model $scheduleOwner): ?int;

    public function quote(BookingRequest $request): BookingQuote
    {
        $this->guard->ensureBookable($request);

        $option = $this->durationOption($request);

        if ($request->startTime === null) {
            throw ReservationNotAllowedException::because('start_time_required');
        }

        $startsAt = $request->date->setTimeFromTimeString($request->startTime);
        $endsAt = $startsAt->addMinutes((int) $option->quantity);

        if (! $endsAt->isSameDay($startsAt)) {
            throw ReservationNotAllowedException::because('outside_schedule');
        }

        $startTime = $startsAt->format('H:i:s');
        $endTime = $endsAt->format('H:i:s');

        $this->guard->ensureNotInPast($request->date, $startTime);

        $owner = $this->scheduleOwner($request);

        if (! $this->guard->fitsSchedule($owner->schedules, $request->date, $startTime, $endTime)) {
            throw ReservationNotAllowedException::because('outside_schedule');
        }

        $this->guard->ensureNotBlocked($request->service, $request->date, $startTime, $endTime);

        $trainerId = $this->trainerId($owner);

        $reserved = $this->guard->reservedQuantity(
            $request->service,
            $request->date,
            $startTime,
            $endTime,
            $trainerId,
            $request->ignoreReservationId,
        );

        $this->guard->ensureCapacity($owner->max_tickets_per_hour, $reserved, $request->quantity);

        $unitPrice = $request->usesPackage() ? 0.0 : (float) $option->price;

        return new BookingQuote(
            date: $request->date,
            quantity: $request->quantity,
            unitPrice: $unitPrice,
            subtotal: round($unitPrice * $request->quantity, 2),
            startTime: $startTime,
            endTime: $endTime,
            priceOptionId: $option->getKey(),
            trainerId: $trainerId,
            clientPackageId: $request->clientPackage?->getKey(),
        );
    }

    /**
     * A package credit fixes the duration to the option the package was sold on.
     *
     * @throws ReservationNotAllowedException
     */
    private function durationOption(BookingRequest $request): PriceOption
    {
        $package = $request->clientPackage;

        if ($package !== null) {
            if ((int) $package->client_id !== (int) $request->client->getKey() || (int) $package->service_id !== (int) $request->service->getKey()) {
                throw ReservationNotAllowedException::because('package_mismatch');
            }

            $usable = $request->isReschedule() ? ! $package->isExpired() : $package->isUsable();

            if (! $usable) {
                throw ReservationNotAllowedException::because('package_not_usable');
            }

            if ($request->quantity !== 1) {
                throw ReservationNotAllowedException::because('package_quantity');
            }
        }

        $optionId = $package?->price_option_id ?? $request->priceOptionId;

        if ($optionId === null) {
            throw ReservationNotAllowedException::because('price_option_required');
        }

        return $request->service->priceOptions()
            ->whereKey($optionId)
            ->where('unit', PriceOptionUnit::MINUTE)
            ->where('quantity', '>', 0)
            ->first() ?? throw ReservationNotAllowedException::because('invalid_price_option');
    }
}
