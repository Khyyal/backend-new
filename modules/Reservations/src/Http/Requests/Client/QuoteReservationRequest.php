<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Prices a slot before booking it, with an optional coupon code.
 */
class QuoteReservationRequest extends FormRequest
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
            ...$this->discountRules(),
        ];
    }
}
