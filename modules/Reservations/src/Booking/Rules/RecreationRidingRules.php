<?php

namespace Modules\Reservations\Booking\Rules;

use Illuminate\Database\Eloquent\Model;
use Modules\Reservations\Booking\BookingRequest;

/**
 * Riding slots follow the riding's own schedules and hourly ticket limit.
 */
class RecreationRidingRules extends HourlySlotRules
{
    protected function scheduleOwner(BookingRequest $request): Model
    {
        return $request->service->serviceable;
    }

    protected function trainerId(Model $scheduleOwner): ?int
    {
        return null;
    }
}
