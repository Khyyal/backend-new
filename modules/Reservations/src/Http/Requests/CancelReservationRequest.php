<?php

namespace Modules\Reservations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Cancels a reservation. Center routes require `manageReservations`; a client
 * may only reach their own reservations.
 */
class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $center = $this->route('center');

        return $center === null || Gate::forUser($this->user())->allows('manageReservations', $center);
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
