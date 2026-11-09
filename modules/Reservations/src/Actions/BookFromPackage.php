<?php

namespace Modules\Reservations\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ClientPackageExhausted;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;

/**
 * Spends one package credit on a single-person reservation. The session is
 * already paid, so the reservation is confirmed straight away. A package
 * bound to a trainer (from a membership) is only booked with that trainer.
 */
class BookFromPackage
{
    public function __construct(private CreateReservation $createReservation) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(
        ClientPackage $clientPackage,
        CarbonImmutable $date,
        string $startTime,
        ReservationSource $source,
        ?int $trainerId = null,
        ?string $notes = null,
    ): Reservation {
        return DB::transaction(function () use ($clientPackage, $date, $startTime, $source, $trainerId, $notes): Reservation {
            $package = ClientPackage::query()
                ->whereKey($clientPackage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($package->trainer_id !== null) {
                if ($trainerId !== null && $trainerId !== $package->trainer_id) {
                    throw ReservationNotAllowedException::because('trainer_not_allowed');
                }

                $trainerId = $package->trainer_id;
            }

            $reservation = $this->createReservation->execute(
                new BookingRequest(
                    service: $package->service,
                    client: $package->client,
                    date: $date,
                    startTime: $startTime,
                    trainerId: $trainerId,
                    clientPackage: $package,
                ),
                $source,
                ReservationStatus::Confirmed,
                $notes,
            );

            $package->remaining_sessions--;

            if ($package->remaining_sessions === 0) {
                $package->status = ClientPackageStatus::Exhausted;

                DB::afterCommit(static function () use ($package): void {
                    event(new ClientPackageExhausted($package));
                });
            }

            $package->save();

            return $reservation;
        });
    }
}
