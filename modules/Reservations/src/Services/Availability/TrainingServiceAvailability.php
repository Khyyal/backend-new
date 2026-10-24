<?php

namespace Modules\Reservations\Services\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reservations\Booking\BookingGuard;
use Modules\Services\Models\Service;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;

/**
 * Hourly slots per trainer of a training service: each trainer's schedule
 * windows under its `max_tickets_per_hour`. A trainer's places are shared by
 * every service they teach, so all of their reservations count.
 */
class TrainingServiceAvailability implements ServiceAvailability
{
    public function __construct(
        private readonly BookingGuard $guard,
        private readonly HourlySlots $slots,
    ) {}

    /**
     * The trainers' schedules and only their reservations taking places in the range.
     *
     * @return array<int|string, string|\Closure>
     */
    public function relations(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'serviceable.trainers.schedules',
            'serviceable.trainers.reservations' => fn (HasMany $query) => $query
                ->occupyingCapacity()
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->select(HourlySlots::RESERVATION_COLUMNS),
        ];
    }

    /**
     * @return array<int, array<int, array{date: string, available: bool, hours: array<int, array<string, mixed>>}>> days keyed by trainer id
     */
    public function forService(Service $service, CarbonImmutable $from, int $days): array
    {
        $from = $from->startOfDay();
        $to = $from->addDays($days - 1);
        $service->loadMissing($this->relations($from, $to));

        /** @var TrainingService $training */
        $training = $service->serviceable;
        $blocks = $this->slots->blocks($service, $from, $to);
        $acceptsBookings = $this->guard->acceptsBookings($service);

        return $training->trainers
            ->mapWithKeys(fn (Trainer $trainer): array => [
                $trainer->getKey() => $this->slots->days(
                    $trainer->schedules,
                    $trainer->max_tickets_per_hour,
                    $trainer->reservations,
                    $blocks,
                    $acceptsBookings,
                    $from,
                    $to,
                ),
            ])
            ->all();
    }
}
