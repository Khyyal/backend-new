<?php

namespace Modules\Reservations\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\Reservation;

/**
 * Books free entries to a membership's visit service. A membership grants up
 * to `max_visits_per_day` entries a day, for the member or anyone they bring;
 * the visit is already paid, so the reservation is confirmed straight away.
 */
class BookVisitFromMembership
{
    public function __construct(private CreateReservation $createReservation) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(
        ClientMembership $clientMembership,
        CarbonImmutable $date,
        ?string $startTime,
        int $quantity,
        ReservationSource $source,
        ?string $notes = null,
    ): Reservation {
        return DB::transaction(function () use ($clientMembership, $date, $startTime, $quantity, $source, $notes): Reservation {
            $membership = ClientMembership::query()
                ->whereKey($clientMembership->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $membership->isUsable() || $date->greaterThanOrEqualTo($membership->expires_at)) {
                throw ReservationNotAllowedException::because('membership_not_usable');
            }

            if (! $membership->grantsVisits() || $membership->visitService === null) {
                throw ReservationNotAllowedException::because('membership_has_no_visits');
            }

            $available = $membership->max_visits_per_day - $this->usedVisits($membership, $date);

            if ($quantity > $available) {
                throw ReservationNotAllowedException::because('membership_daily_visits_exceeded', ['available' => max(0, $available)]);
            }

            return $this->createReservation->execute(
                new BookingRequest(
                    service: $membership->visitService,
                    client: $membership->client,
                    date: $date,
                    startTime: $startTime,
                    quantity: $quantity,
                    clientMembership: $membership,
                ),
                $source,
                ReservationStatus::Confirmed,
                $notes,
            );
        });
    }

    /**
     * Free visits of the membership already holding a place on the day.
     */
    public function usedVisits(ClientMembership $membership, CarbonImmutable $date): int
    {
        return (int) $membership->reservations()
            ->occupyingCapacity()
            ->whereDate('date', $date->toDateString())
            ->sum('quantity');
    }
}
