<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Center staff book a walk-in for a client: paid at the desk (`manual`), on
 * arrival, or online by the client.
 */
class StoreReservationRequest extends FormRequest
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
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            ...$this->paymentCompanyRules(),
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
