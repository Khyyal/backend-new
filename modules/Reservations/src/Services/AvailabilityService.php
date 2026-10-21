<?php

namespace Modules\Reservations\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Event;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Models\ServicePeriod;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\Visit;
use Modules\Support\Enums\ActivationStatus;

/**
 * Day-by-day view of what a service offers in a date range: its open windows,
 * blocked times, places already taken and gender periods (informational).
 * It describes the calendar; the booking rules still decide each booking.
 */
class AvailabilityService
{
    public const int MAX_DAYS = 31;

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws ReservationNotAllowedException
     */
    public function forService(Service $service, CarbonImmutable $from, CarbonImmutable $to, ?int $trainerId = null): array
    {
        if ($service->status !== ActivationStatus::ACTIVE) {
            throw ReservationNotAllowedException::because('service_inactive');
        }

        $trainer = $service->type === ServiceType::Training ? $this->trainer($service, $trainerId) : null;
        $reservations = $this->reservations($service, $from, $to, $trainer);
        $blocks = ServiceBlock::query()
            ->forService($service)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->with('periods')
            ->get();
        $periods = $service->periods()->get();

        $days = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $dayBlocks = $blocks->filter(fn (ServiceBlock $block): bool => $block->start_date->lte($date) && $block->end_date->gte($date));
            $blockedAllDay = $dayBlocks->contains(fn (ServiceBlock $block): bool => $block->time_scope === BlockTimeScope::AllDay);

            $days[] = [
                'date' => $date->toDateString(),
                'day_of_week' => $date->dayOfWeek,
                'is_past' => $date->endOfDay()->isPast(),
                'blocked' => $blockedAllDay,
                'blocked_periods' => $dayBlocks
                    ->where('time_scope', BlockTimeScope::SpecificTime)
                    ->flatMap(fn (ServiceBlock $block) => $block->periods)
                    ->map(fn ($period): array => ['start' => self::hour($period->start_time), 'end' => self::hour($period->end_time)])
                    ->values()
                    ->all(),
                'periods' => $periods
                    ->where('day_of_week', $date->dayOfWeek)
                    ->map(fn (ServicePeriod $period): array => [
                        'gender' => $period->gender->value,
                        'start' => self::hour($period->start_time),
                        'end' => self::hour($period->end_time),
                    ])
                    ->values()
                    ->all(),
                ...$this->dayDetails($service, $date, $reservations, $trainer),
            ];
        }

        return $days;
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<string, mixed>
     */
    private function dayDetails(Service $service, CarbonImmutable $date, Collection $reservations, ?Trainer $trainer): array
    {
        $serviceable = $service->serviceable;
        $dayReservations = $reservations->filter(fn (Reservation $reservation): bool => $reservation->date->isSameDay($date));

        return match ($service->type) {
            ServiceType::RecreationRiding, ServiceType::Training => $this->hourly(
                ($trainer ?? $serviceable)->schedules,
                ($trainer ?? $serviceable)->max_tickets_per_hour,
                $date,
                $dayReservations,
            ),
            ServiceType::Visit => $this->visit($serviceable, $date, $dayReservations),
            ServiceType::Event => $this->event($serviceable, $date, $dayReservations),
            ServiceType::Resort => $this->resort($serviceable, $date, $reservations),
            default => throw ReservationNotAllowedException::because('service_type_not_bookable'),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Schedule>  $schedules
     * @param  Collection<int, Reservation>  $dayReservations
     * @return array<string, mixed>
     */
    private function hourly($schedules, ?int $capacityPerHour, CarbonImmutable $date, Collection $dayReservations): array
    {
        return [
            'capacity_per_hour' => $capacityPerHour,
            'windows' => $this->windows($schedules, $date),
            'booked' => $dayReservations
                ->groupBy(fn (Reservation $reservation): string => $reservation->start_time.'-'.$reservation->end_time)
                ->map(fn (Collection $group): array => [
                    'start' => self::hour($group->first()->start_time),
                    'end' => self::hour($group->first()->end_time),
                    'quantity' => (int) $group->sum('quantity'),
                ])
                ->sortBy('start')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $dayReservations
     * @return array<string, mixed>
     */
    private function visit(Visit $visit, CarbonImmutable $date, Collection $dayReservations): array
    {
        $isOpen = $visit->schedules->contains('day_of_week', $date->dayOfWeek);

        return [
            'open' => $isOpen,
            'windows' => $visit->enter_type === VisitEnterType::SpecificTime ? $this->windows($visit->schedules, $date) : [],
            ...$this->dailyCapacity($visit->max_tickets_per_day, $dayReservations),
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $dayReservations
     * @return array<string, mixed>
     */
    private function event(Event $event, CarbonImmutable $date, Collection $dayReservations): array
    {
        $day = $date->startOfDay();
        $isOpen = ($event->start_date === null || $day->gte($event->start_date->startOfDay()))
            && ($event->end_date === null || $day->lte($event->end_date->startOfDay()));

        return [
            'open' => $isOpen,
            ...$this->dailyCapacity($event->max_tickets_per_day, $dayReservations),
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations  every stay overlapping the range
     * @return array<string, mixed>
     */
    private function resort(Resort $resort, CarbonImmutable $date, Collection $reservations): array
    {
        $price = $resort->dayPrices->first(fn ($option): bool => (int) $option->quantity === $date->dayOfWeek)?->price;
        $isReserved = $reservations->contains(fn (Reservation $reservation): bool => $reservation->date->lte($date)
            && ($reservation->end_date ?? $reservation->date)->gte($date));

        return [
            'open' => $price !== null,
            'price' => $price !== null ? (float) $price : null,
            'reserved' => $isReserved,
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $dayReservations
     * @return array{capacity: int|null, reserved: int, available: int|null}
     */
    private function dailyCapacity(?int $capacity, Collection $dayReservations): array
    {
        $reserved = (int) $dayReservations->sum('quantity');

        return [
            'capacity' => $capacity,
            'reserved' => $reserved,
            'available' => $capacity === null ? null : max(0, $capacity - $reserved),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Schedule>  $schedules
     * @return array<int, array{start: string, end: string}>
     */
    private function windows($schedules, CarbonImmutable $date): array
    {
        return $schedules
            ->where('day_of_week', $date->dayOfWeek)
            ->filter(fn (Schedule $schedule): bool => $schedule->start_time !== null && $schedule->end_time !== null)
            ->map(fn (Schedule $schedule): array => ['start' => self::hour($schedule->start_time), 'end' => self::hour($schedule->end_time)])
            ->sortBy('start')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function reservations(Service $service, CarbonImmutable $from, CarbonImmutable $to, ?Trainer $trainer): Collection
    {
        return Reservation::query()
            ->occupyingCapacity()
            ->when(
                $trainer !== null,
                fn (Builder $query) => $query->where('trainer_id', $trainer->getKey()),
                fn (Builder $query) => $query->where('service_id', $service->getKey()),
            )
            ->whereDate('date', '<=', $to->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereDate('date', '>=', $from->toDateString())
                ->orWhereDate('end_date', '>=', $from->toDateString()))
            ->get();
    }

    /**
     * @throws ReservationNotAllowedException
     */
    private function trainer(Service $service, ?int $trainerId): Trainer
    {
        if ($trainerId === null) {
            throw ReservationNotAllowedException::because('trainer_required');
        }

        return Trainer::query()
            ->whereKey($trainerId)
            ->where('center_id', $service->center_id)
            ->whereHas('trainingServices', fn (Builder $query) => $query->whereKey($service->serviceable_id))
            ->with('schedules')
            ->first() ?? throw ReservationNotAllowedException::because('invalid_trainer');
    }

    private static function hour(string $time): string
    {
        return substr(BookingGuard::normalizeTime($time), 0, 5);
    }
}
