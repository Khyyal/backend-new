<?php

namespace Modules\Reservations\Booking;

use Illuminate\Contracts\Container\Container;
use Modules\Reservations\Booking\Rules\EventRules;
use Modules\Reservations\Booking\Rules\RecreationRidingRules;
use Modules\Reservations\Booking\Rules\ResortRules;
use Modules\Reservations\Booking\Rules\TrainingRules;
use Modules\Reservations\Booking\Rules\VisitRules;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * Picks the booking rules of a service from its type.
 */
class ReservationRulesResolver
{
    public function __construct(private Container $container) {}

    /**
     * @throws ReservationNotAllowedException
     */
    public function for(Service $service): ReservationRules
    {
        $rules = match ($service->type) {
            ServiceType::RecreationRiding => RecreationRidingRules::class,
            ServiceType::Training => TrainingRules::class,
            ServiceType::Visit => VisitRules::class,
            ServiceType::Event => EventRules::class,
            ServiceType::Resort => ResortRules::class,
            default => throw ReservationNotAllowedException::because('service_type_not_bookable'),
        };

        return $this->container->make($rules);
    }

    /**
     * @throws ReservationNotAllowedException
     */
    public function quote(BookingRequest $request): BookingQuote
    {
        return $this->for($request->service)->quote($request);
    }
}
