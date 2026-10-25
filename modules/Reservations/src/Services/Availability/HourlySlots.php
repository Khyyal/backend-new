<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Models\ServiceBlockPeriod;

/**
 * Day-by-day one-hour slots of a schedule owner (a riding or a trainer), each
 * with the tickets left under its hourly limit. A slot is available when the
 * service accepts bookings, it has not started, is not blocked and still has
 * tickets; a day is available when any of its slots is.
 */
final class HourlySlots
{
    /**
     * Columns of a reservation the slots need.
     *
     * @var list<string>
     */
    public const array RESERVATION_COLUMNS = ['id', 'service_id', 'trainer_id', 'date', 'start_time', 'end_time', 'quantity'];

    /**
     * Blocks of the service (or its whole center) overlapping the range, with their periods.
     *
     * @return Collection<int, ServiceBlock>
     */
    public function blocks(Service $service, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return ServiceBlock::query()
            ->forService($service)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->with('periods')
            ->get();
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @param  Collection<int, Reservation>  $reservations  taking places in the range
     * @param  Collection<int, ServiceBlock>  $blocks
     * @return array<int, array{date: string, available: bool, hours: array<int, array{hour: string, available: bool, available_tickets: int|null, max_tickets: int|null}>}>
     */
    public function days(
        Collection $schedules,
        ?int $maxTickets,
        Collection $reservations,
        Collection $blocks,
        bool $acceptsBookings,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $reservationsByDate = $reservations->groupBy(fn (Reservation $reservation): string => $reservation->date->toDateString());
        $result = [];

        for ($date = $from->startOfDay(); $date->lte($to); $date = $date->addDay()) {
            $hours = $this->hours(
                $schedules,
                $maxTickets,
                $date,
                $reservationsByDate->get($date->toDateString(), collect()),
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
     * @param  Collection<int, Schedule>  $schedules
     * @param  Collection<int, Reservation>  $dayReservations
     * @param  Collection<int, ServiceBlock>  $dayBlocks
     * @return array<int, array{hour: string, available: bool, available_tickets: int|null, max_tickets: int|null}>
     */
    private function hours(
        Collection $schedules,
        ?int $maxTickets,
        CarbonImmutable $date,
        Collection $dayReservations,
        Collection $dayBlocks,
        bool $acceptsBookings,
    ): array {
        $blockedAllDay = $dayBlocks->contains(fn (ServiceBlock $block): bool => $block->time_scope === BlockTimeScope::AllDay);
        $blockedPeriods = $dayBlocks
            ->where('time_scope', BlockTimeScope::SpecificTime)
            ->flatMap(fn (ServiceBlock $block) => $block->periods);

        return $this->slots($schedules, $date)
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
     * @param  Collection<int, Schedule>  $schedules
     * @return Collection<int, CarbonImmutable>
     */
    private function slots(Collection $schedules, CarbonImmutable $date): Collection
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
