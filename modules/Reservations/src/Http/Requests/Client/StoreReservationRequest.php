<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * A client books one slot of a service and pays online or on arrival.
 */
class StoreReservationRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->bookingRules(),
            'payment_method' => ['required', Rule::in([PaymentMethod::Online->value, PaymentMethod::CashOnArrival->value])],
            ...$this->paymentCompanyRules(),
        ];
    }
}
