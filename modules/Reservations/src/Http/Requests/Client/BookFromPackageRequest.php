<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Spends one package credit on a slot.
 */
class BookFromPackageRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->slotRules(),
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
