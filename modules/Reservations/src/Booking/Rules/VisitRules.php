<?php

namespace Modules\Reservations\Booking\Rules;

use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Booking\BookingQuote;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\ReservationRules;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Visit;

/**
 * An all-day visit needs only an open day; a specific-time visit books one of
 * the day's schedule windows. Both share the daily ticket limit.
 */
class VisitRules implements ReservationRules
{
    public function __construct(private BookingGuard $guard) {}

    public function quote(BookingRequest $request): BookingQuote
    {
        if ($request->usesPackage()) {
            throw ReservationNotAllowedException::because('package_not_supported');
        }

        $this->guard->ensureBookable($request);

        /** @var Visit $visit */
        $visit = $request->service->serviceable;
        $daySchedules = $visit->schedules->where('day_of_week', $request->date->dayOfWeek);

        [$startTime, $endTime] = $visit->enter_type === VisitEnterType::SpecificTime
            ? $this->window($request, $daySchedules)
            : [null, null];

        if ($daySchedules->isEmpty()) {
            throw ReservationNotAllowedException::because('outside_schedule');
        }

        $this->guard->ensureNotInPast($request->date, $startTime);
        $this->guard->ensureNotBlocked($request->service, $request->date, $startTime, $endTime);

        $reserved = $this->guard->reservedQuantity($request->service, $request->date, ignoreReservationId: $request->ignoreReservationId);
        $this->guard->ensureCapacity($visit->max_tickets_per_day, $reserved, $request->quantity);

        $option = $request->service->priceOptions()->first();
        $unitPrice = (float) ($option?->price ?? 0);

        return new BookingQuote(
            date: $request->date,
            quantity: $request->quantity,
            unitPrice: $unitPrice,
            subtotal: round($unitPrice * $request->quantity, 2),
            startTime: $startTime,
            endTime: $endTime,
            priceOptionId: $option?->getKey(),
        );
    }

    /**
     * @param  iterable<int, Schedule>  $daySchedules
     * @return array{0: string, 1: string}
     *
     * @throws ReservationNotAllowedException
     */
    private function window(BookingRequest $request, iterable $daySchedules): array
    {
        if ($request->startTime === null) {
            throw ReservationNotAllowedException::because('start_time_required');
        }

        $startTime = BookingGuard::normalizeTime($request->startTime);

        foreach ($daySchedules as $schedule) {
            if ($schedule->start_time !== null && $schedule->end_time !== null
                && BookingGuard::normalizeTime($schedule->start_time) === $startTime) {
                return [$startTime, BookingGuard::normalizeTime($schedule->end_time)];
            }
        }

        throw ReservationNotAllowedException::because('outside_schedule');
    }
}
