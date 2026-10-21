<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Moves a reservation to another slot of the same service.
 */
class RescheduleReservationRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageReservations', $this->route('center'));
    }

    public function rules(): array
    {
        return $this->slotRules();
    }
}
