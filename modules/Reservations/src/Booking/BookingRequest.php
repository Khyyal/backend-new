<?php

namespace Modules\Reservations\Booking;

use Carbon\CarbonImmutable;
use Modules\Clients\Models\Client;
use Modules\Reservations\Models\ClientPackage;
use Modules\Services\Models\Service;

/**
 * What a client (or center staff on their behalf) asks to book.
 */
final readonly class BookingRequest
{
    /**
     * @param  string|null  $startTime  `H:i`, for hour-based services
     * @param  CarbonImmutable|null  $endDate  inclusive last day, for resorts
     * @param  array<int, array{price_option_id: int, quantity: int}>  $lines  event ticket / resort addons
     * @param  int|null  $ignoreReservationId  reservation being rescheduled, left out of capacity counts
     */
    public function __construct(
        public Service $service,
        public Client $client,
        public CarbonImmutable $date,
        public ?string $startTime = null,
        public ?CarbonImmutable $endDate = null,
        public int $quantity = 1,
        public ?int $priceOptionId = null,
        public ?int $trainerId = null,
        public array $lines = [],
        public ?ClientPackage $clientPackage = null,
        public ?int $ignoreReservationId = null,
    ) {}

    public function usesPackage(): bool
    {
        return $this->clientPackage !== null;
    }

    /**
     * Moving an existing reservation: its own places and package credit are already taken.
     */
    public function isReschedule(): bool
    {
        return $this->ignoreReservationId !== null;
    }
}
