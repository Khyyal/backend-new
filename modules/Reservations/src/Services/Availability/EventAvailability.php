<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\EventOccurrenceType;
use Modules\Services\Models\Event;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceBlock;

/**
 * Event days (start_date..end_date) of a `specific_day` event, each with the
 * tickets left under `max_tickets_per_day`. A day is available when the
 * service accepts bookings, booking is open today (open_date..close_date),
 * the day has not passed, is not blocked all day and still has tickets.
 * `general` events have no days.
 */
class EventAvailability implements ServiceAvailability
{
    public function __construct(
        private readonly BookingGuard $guard,
        private readonly HourlySlots $slots,
    ) {}

    /**
     * Only the reservations taking event places in the range, with the columns the days need.
     *
     * @return array<int|string, string|\Closure>
     */
    public function relations(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'reservations' => fn (HasMany $query) => $query
                ->occupyingCapacity()
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->select(['id', 'service_id', 'date', 'quantity']),
        ];
    }

    /**
     * The range is the event's own dates; `$from` / `$days` do not apply.
     *
     * @return array<int, array{day: string, available: bool, available_tickets: int|null, max_tickets: int|null}>
     */
    public function forService(Service $service, CarbonImmutable $from, int $days): array
    {
        /** @var Event $event */
        $event = $service->serviceable;

        if ($event->occurrence_type !== EventOccurrenceType::SpecificDay || $event->start_date === null || $event->end_date === null) {
            return [];
        }

        $start = CarbonImmutable::parse($event->start_date)->startOfDay();
        $end = CarbonImmutable::parse($event->end_date)->startOfDay();
        $maxTickets = $event->max_tickets_per_day;
        $isOpen = $this->guard->acceptsBookings($service) && $this->bookingOpenToday($event);

        $reserved = $service->loadMissing($this->relations($start, $end))->reservations
            ->groupBy(fn (Reservation $reservation): string => $reservation->date->toDateString())
            ->map(fn ($group): int => (int) $group->sum('quantity'));
        $blockedDays = $this->slots->blocks($service, $start, $end)
            ->where('time_scope', BlockTimeScope::AllDay);

        $result = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $availableTickets = $maxTickets === null ? null : max(0, $maxTickets - $reserved->get($day->toDateString(), 0));
            $isBlocked = $blockedDays->contains(fn (ServiceBlock $block): bool => $block->start_date->lte($day) && $block->end_date->gte($day));

            $result[] = [
                'day' => $day->toDateString(),
                'available' => $isOpen
                    && ! $isBlocked
                    && ! $day->endOfDay()->isPast()
                    && ($availableTickets === null || $availableTickets > 0),
                'available_tickets' => $availableTickets,
                'max_tickets' => $maxTickets,
            ];
        }

        return $result;
    }

    private function bookingOpenToday(Event $event): bool
    {
        $today = now()->startOfDay();

        return ($event->open_date === null || $today->gte($event->open_date->startOfDay()))
            && ($event->close_date === null || $today->lte($event->close_date->startOfDay()));
    }
}
