<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Center staff price a walk-in for a client before booking it.
 */
class QuoteReservationRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageReservations', $this->route('center'));
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            ...$this->bookingRules(),
            ...$this->discountRules(),
        ];
    }
}
