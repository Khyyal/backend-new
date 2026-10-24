<?php

namespace Modules\Reservations\Booking;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Clients\Models\Client;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceBlock;
use Modules\Services\Models\ServiceSetting;
use Modules\Support\Enums\ActivationStatus;

/**
 * Checks shared by every service type's booking rules.
 */
class BookingGuard
{
    /**
     * Resort settings that close every booking of a service type in the center.
     *
     * @var array<string, string>
     */
    private const array CLOSING_SETTINGS = [
        ServiceType::Training->value => 'close_all_training_bookings',
        ServiceType::Visit->value => 'close_all_visit_bookings',
        ServiceType::RecreationRiding->value => 'close_all_recreational_ride_bookings',
        ServiceType::Resort->value => 'close_all_resort_bookings',
    ];

    /**
     * Service, client and settings checks that do not depend on the slot.
     *
     * @throws ReservationNotAllowedException
     */
    public function ensureBookable(BookingRequest $request): void
    {
        if ($request->quantity < 1) {
            throw ReservationNotAllowedException::because('invalid_quantity');
        }

        $this->ensureServiceOpenTo($request->service, $request->client);

        if ($request->usesPackage()) {
            $this->ensureSettingEnabled($request->service, 'enable_package_booking', 'package_booking_disabled');
        } else {
            $this->ensureSettingEnabled($request->service, 'enable_single_session_booking', 'single_session_booking_disabled');
        }
    }

    /**
     * The service is active, open for bookings and the client is not blocked by its center.
     *
     * @throws ReservationNotAllowedException
     */
    public function ensureServiceOpenTo(Service $service, Client $client): void
    {
        if ($service->status !== ActivationStatus::ACTIVE) {
            throw ReservationNotAllowedException::because('service_inactive');
        }

        $isBlocked = $service->center->blocksGiven()
            ->where('blockable_type', $client->getMorphClass())
            ->where('blockable_id', $client->getKey())
            ->exists();

        if ($isBlocked) {
            throw ReservationNotAllowedException::because('client_blocked');
        }

        if ($this->closedByCenter($service)) {
            throw ReservationNotAllowedException::because('bookings_closed');
        }
    }

    /**
     * Whether any client could book the service at all: it is active, not
     * closed by its center and at least one booking mode is enabled.
     */
    public function acceptsBookings(Service $service): bool
    {
        if ($service->status !== ActivationStatus::ACTIVE || $this->closedByCenter($service)) {
            return false;
        }

        return collect(['enable_single_session_booking', 'enable_package_booking'])
            ->contains(fn (string $key): bool => ! $this->supportsSetting($service, $key) || $this->settingEnabled($service, $key));
    }

    /**
     * A setting the service type does not declare never blocks the booking.
     *
     * @throws ReservationNotAllowedException
     */
    public function ensureSettingEnabled(Service $service, string $key, string $reason): void
    {
        if ($this->supportsSetting($service, $key) && ! $this->settingEnabled($service, $key)) {
            throw ReservationNotAllowedException::because($reason);
        }
    }

    /**
     * @throws ReservationNotAllowedException
     */
    public function ensureNotInPast(CarbonImmutable $date, ?string $startTime = null): void
    {
        $startsAt = $startTime === null ? $date->endOfDay() : $date->setTimeFromTimeString($startTime);

        if ($startsAt->isPast()) {
            throw ReservationNotAllowedException::because('date_in_past');
        }
    }

    /**
     * A day-only booking is blocked by all-day blocks; a timed one also by
     * time-scoped blocks whose periods overlap it.
     *
     * @throws ReservationNotAllowedException
     */
    public function ensureNotBlocked(Service $service, CarbonImmutable $date, ?string $startTime = null, ?string $endTime = null): void
    {
        $day = $date->toDateString();

        $blocked = ServiceBlock::query()
            ->forService($service)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->where(function (Builder $query) use ($startTime, $endTime): void {
                $query->where('time_scope', BlockTimeScope::AllDay);

                if ($startTime !== null && $endTime !== null) {
                    $query->orWhere(function (Builder $query) use ($startTime, $endTime): void {
                        $query->where('time_scope', BlockTimeScope::SpecificTime)
                            ->whereHas('periods', function (Builder $periods) use ($startTime, $endTime): void {
                                $periods->where('start_time', '<', $endTime)->where('end_time', '>', $startTime);
                            });
                    });
                }
            })
            ->exists();

        if ($blocked) {
            throw ReservationNotAllowedException::because('slot_blocked');
        }
    }

    /**
     * Whether [start, end) fits entirely inside one of the given schedule windows.
     *
     * @param  Collection<int, Schedule>  $schedules
     */
    public function fitsSchedule(Collection $schedules, CarbonImmutable $date, string $startTime, string $endTime): bool
    {
        return $schedules
            ->where('day_of_week', $date->dayOfWeek)
            ->contains(fn (Schedule $schedule): bool => $schedule->start_time !== null
                && $schedule->end_time !== null
                && self::normalizeTime($schedule->start_time) <= $startTime
                && self::normalizeTime($schedule->end_time) >= $endTime);
    }

    /**
     * Places already taken on a day, optionally only by reservations overlapping
     * [start, end) and/or held by one trainer.
     */
    public function reservedQuantity(
        Service $service,
        CarbonImmutable $date,
        ?string $startTime = null,
        ?string $endTime = null,
        ?int $trainerId = null,
        ?int $ignoreReservationId = null,
    ): int {
        return (int) Reservation::query()
            ->occupyingCapacity()
            ->when(
                $trainerId !== null,
                fn (Builder $query) => $query->where('trainer_id', $trainerId),
                fn (Builder $query) => $query->where('service_id', $service->getKey()),
            )
            ->whereDate('date', $date->toDateString())
            ->when($startTime !== null && $endTime !== null, fn (Builder $query) => $query
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime))
            ->when($ignoreReservationId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreReservationId))
            ->sum('quantity');
    }

    /**
     * @throws ReservationNotAllowedException
     */
    public function ensureCapacity(?int $capacity, int $reserved, int $requested): void
    {
        if ($capacity === null) {
            return;
        }

        $available = max(0, $capacity - $reserved);

        if ($requested > $available) {
            throw ReservationNotAllowedException::because('capacity_exceeded', ['available' => $available]);
        }
    }

    public static function normalizeTime(string $time): string
    {
        return CarbonImmutable::parse($time)->format('H:i:s');
    }

    private function closedByCenter(Service $service): bool
    {
        $key = self::CLOSING_SETTINGS[$service->type->value] ?? null;

        if ($key === null) {
            return false;
        }

        return ServiceSetting::query()
            ->where('key', $key)
            ->whereIn('value', ['1', 'true'])
            ->whereHas('service', fn (Builder $query) => $query
                ->where('center_id', $service->center_id)
                ->where('type', ServiceType::Resort)
                ->where('status', ActivationStatus::ACTIVE))
            ->exists();
    }

    private function supportsSetting(Service $service, string $key): bool
    {
        $serviceable = $service->serviceable;

        return $serviceable !== null
            && method_exists($serviceable, 'settingKeys')
            && in_array($key, $serviceable::settingKeys(), true);
    }

    private function settingEnabled(Service $service, string $key): bool
    {
        return (bool) $service->settings->firstWhere('key', $key)?->isEnabled();
    }
}
