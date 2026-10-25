<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Service;

/**
 * Hourly slots of a riding's schedule windows under its `max_tickets_per_hour`.
 */
class RecreationRidingAvailability implements ServiceAvailability
{
    public function __construct(
        private readonly BookingGuard $guard,
        private readonly HourlySlots $slots,
    ) {}

    /**
     * Only the reservations taking riding places in the range, with the columns the slots need.
     *
     * @return array<string, \Closure>
     */
    public function relations(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'reservations' => fn (HasMany $query) => $query
                ->occupyingCapacity()
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->select(HourlySlots::RESERVATION_COLUMNS),
        ];
    }

    /**
     * @return array<int, array{date: string, available: bool, hours: array<int, array{hour: string, available: bool, available_tickets: int|null, max_tickets: int|null}>}>
     */
    public function forService(Service $service, CarbonImmutable $from, int $days): array
    {
        /** @var RecreationalRiding $riding */
        $riding = $service->serviceable;
        $from = $from->startOfDay();
        $to = $from->addDays($days - 1);

        return $this->slots->days(
            $riding->schedules,
            $riding->max_tickets_per_hour,
            $service->loadMissing($this->relations($from, $to))->reservations,
            $this->slots->blocks($service, $from, $to),
            $this->guard->acceptsBookings($service),
            $from,
            $to,
        );
    }
}
