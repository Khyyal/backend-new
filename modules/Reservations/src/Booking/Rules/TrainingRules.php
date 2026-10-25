<?php

namespace Modules\Reservations\Booking\Rules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Exceptions\ReservationNotAllowedException;
use Modules\Services\Models\Trainer;

/**
 * Training slots follow the chosen trainer's schedules and hourly ticket
 * limit; capacity is shared across every service the trainer teaches.
 */
class TrainingRules extends HourlySlotRules
{
    protected function scheduleOwner(BookingRequest $request): Model
    {
        if ($request->trainerId === null) {
            throw ReservationNotAllowedException::because('trainer_required');
        }

        $service = $request->service;

        return Trainer::query()
            ->whereKey($request->trainerId)
            ->where('center_id', $service->center_id)
            ->whereHas('trainingServices', fn (Builder $query) => $query->whereKey($service->serviceable_id))
            ->with('schedules')
            ->first() ?? throw ReservationNotAllowedException::because('invalid_trainer');
    }

    protected function trainerId(Model $scheduleOwner): ?int
    {
        return $scheduleOwner->getKey();
    }
}
