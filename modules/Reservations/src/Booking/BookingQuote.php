<?php

namespace Modules\Reservations\Booking;

use Carbon\CarbonImmutable;
use Modules\Reservations\Enums\ReservationLineKind;

/**
 * A validated and priced booking, ready to be stored as a reservation.
 */
final readonly class BookingQuote
{
    /**
     * @param  array<int, array{price_option_id: int, kind: ReservationLineKind, quantity: int, unit_price: float, subtotal: float}>  $lines
     */
    public function __construct(
        public CarbonImmutable $date,
        public int $quantity,
        public float $unitPrice,
        public float $subtotal,
        public ?CarbonImmutable $endDate = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?int $priceOptionId = null,
        public ?int $trainerId = null,
        public ?int $clientPackageId = null,
        public array $lines = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toReservationAttributes(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'end_date' => $this->endDate?->toDateString(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->subtotal,
            'total_amount' => $this->subtotal,
            'price_option_id' => $this->priceOptionId,
            'trainer_id' => $this->trainerId,
            'client_package_id' => $this->clientPackageId,
        ];
    }
}
