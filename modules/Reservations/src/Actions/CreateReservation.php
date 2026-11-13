<?php

namespace Modules\Reservations\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\CapacityLock;
use Modules\Reservations\Booking\ReservationRulesResolver;
use Modules\Reservations\Enums\ReservationSource;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Events\ReservationCreated;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Reservations\Models\Reservation;

/**
 * Validates, prices and stores a reservation while holding the capacity lock,
 * so two concurrent bookings cannot both take the last places.
 */
class CreateReservation
{
    public function __construct(
        private ReservationRulesResolver $rules,
        private CapacityLock $capacityLock,
    ) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function execute(
        BookingRequest $request,
        ReservationSource $source,
        ReservationStatus $status = ReservationStatus::Pending,
        ?string $notes = null,
    ): Reservation {
        return DB::transaction(function () use ($request, $source, $status, $notes): Reservation {
            $this->capacityLock->acquire($request->service, $request->trainerId, $request->horseId);

            $quote = $this->rules->quote($request);

            $attributes = $quote->toReservationAttributes();

            if ($request->totalOverride !== null) {
                $attributes['total_amount'] = $request->totalOverride;
                $attributes['metadata'] = ['price_override' => ['calculated_total' => $quote->subtotal]];
            }

            $reservation = Reservation::query()->create([
                ...$attributes,
                'center_id' => $request->service->center_id,
                'service_id' => $request->service->getKey(),
                'client_id' => $request->client->getKey(),
                'source' => $source,
                'status' => $status,
                'notes' => $notes,
            ]);

            foreach ($quote->lines as $line) {
                $reservation->lines()->create($line);
            }

            DB::afterCommit(static function () use ($reservation): void {
                event(new ReservationCreated($reservation));
            });

            return $reservation->load('lines');
        });
    }
}
