<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Spends free daily visits of a membership on its visit service.
 */
class BookVisitFromMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
