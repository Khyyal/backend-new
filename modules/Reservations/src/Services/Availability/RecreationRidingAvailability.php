<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Models\ServiceBlockPeriod;

/**
 * Hourly slots of a riding's schedule windows, each with the tickets left
 * under its `max_tickets_per_hour`. A slot is available when it has not
 * started, is not blocked and still has tickets.
 */
class RecreationRidingAvailability implements ServiceAvailability
{
    public function __construct(private readonly BookingGuard $guard) {}

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
                ->select(['id', 'service_id', 'date', 'start_time', 'end_time', 'quantity']),
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
        $acceptsBookings = $this->guard->acceptsBookings($service);

        $reservations = $service->loadMissing($this->relations($from, $to))->reservations
            ->groupBy(fn (Reservation $reservation): string => $reservation->date->toDateString());

        $blocks = ServiceBlock::query()
            ->forService($service)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->with('periods')
            ->get();

        $result = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $hours = $this->hours(
                $riding,
                $date,
                $reservations->get($date->toDateString(), new Collection),
                $blocks->filter(fn (ServiceBlock $block): bool => $block->start_date->lte($date) && $block->end_date->gte($date)),
                $acceptsBookings,
            );

            $result[] = [
                'date' => $date->toDateString(),
                'available' => collect($hours)->contains('available', true),
                'hours' => $hours,
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, Reservation>  $dayReservations
     * @param  \Illuminate\Support\Collection<int, ServiceBlock>  $dayBlocks
     * @return array<int, array{hour: string, available: bool, available_tickets: int|null, max_tickets: int|null}>
     */
    private function hours(RecreationalRiding $riding, CarbonImmutable $date, Collection $dayReservations, $dayBlocks, bool $acceptsBookings): array
    {
        $maxTickets = $riding->max_tickets_per_hour;
        $blockedAllDay = $dayBlocks->contains(fn (ServiceBlock $block): bool => $block->time_scope === BlockTimeScope::AllDay);
        $blockedPeriods = $dayBlocks
            ->where('time_scope', BlockTimeScope::SpecificTime)
            ->flatMap(fn (ServiceBlock $block) => $block->periods);

        return $this->slots($riding->schedules, $date)
            ->map(function (CarbonImmutable $startsAt) use ($maxTickets, $blockedAllDay, $blockedPeriods, $dayReservations, $acceptsBookings): array {
                $start = $startsAt->format('H:i:s');
                $end = $startsAt->addHour()->format('H:i:s');

                $reserved = (int) $dayReservations
                    ->filter(fn (Reservation $reservation): bool => BookingGuard::normalizeTime($reservation->start_time) < $end
                        && BookingGuard::normalizeTime($reservation->end_time) > $start)
                    ->sum('quantity');
                $availableTickets = $maxTickets === null ? null : max(0, $maxTickets - $reserved);

                $isBlocked = $blockedAllDay || $blockedPeriods->contains(
                    fn (ServiceBlockPeriod $period): bool => BookingGuard::normalizeTime($period->start_time) < $end
                        && BookingGuard::normalizeTime($period->end_time) > $start
                );

                return [
                    'hour' => $startsAt->format('H:i'),
                    'available' => $acceptsBookings
                        && ! $isBlocked
                        && $startsAt->isFuture()
                        && ($availableTickets === null || $availableTickets > 0),
                    'available_tickets' => $availableTickets,
                    'max_tickets' => $maxTickets,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Start times of the one-hour slots that fit inside the day's schedule windows.
     *
     * @param  \Illuminate\Support\Collection<int, Schedule>  $schedules
     * @return \Illuminate\Support\Collection<int, CarbonImmutable>
     */
    private function slots($schedules, CarbonImmutable $date): \Illuminate\Support\Collection
    {
        return $schedules
            ->where('day_of_week', $date->dayOfWeek)
            ->filter(fn (Schedule $schedule): bool => $schedule->start_time !== null && $schedule->end_time !== null)
            ->flatMap(function (Schedule $schedule) use ($date): array {
                $windowEnd = $date->setTimeFromTimeString($schedule->end_time);
                $slots = [];

                for ($slot = $date->setTimeFromTimeString($schedule->start_time); $slot->addHour()->lte($windowEnd); $slot = $slot->addHour()) {
                    $slots[] = $slot;
                }

                return $slots;
            })
            ->unique(fn (CarbonImmutable $slot): string => $slot->format('H:i'))
            ->sortBy(fn (CarbonImmutable $slot): string => $slot->format('H:i'))
            ->values();
    }
}
