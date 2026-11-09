<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Spends free daily visits of a membership on its visit service.
 */
#[BodyParam('date', 'string', 'Day before the membership expires, `Y-m-d`.', example: '2026-06-06')]
#[BodyParam('start_time', 'string', 'Start `H:i`; required for timed visits.', required: false, nullable: true, example: 'No-example')]
#[BodyParam('quantity', 'integer', 'Free visits to spend, 1-100. Default 1.', required: false, example: 1)]
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
