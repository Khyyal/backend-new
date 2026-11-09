<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Reservations\Services\AvailabilityService;

/**
 * A date range of at most AvailabilityService::MAX_DAYS days.
 */
#[QueryParam('from', 'string', 'First day, `Y-m-d`.', required: true, example: '2026-06-01')]
#[QueryParam('to', 'string', 'Last day, `Y-m-d`, at most 31 days after `from`.', required: true, example: '2026-06-07')]
#[QueryParam('trainer_id', 'integer', 'Trainer; required for training services.', required: false, example: 6)]
class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'trainer_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to'))) + 1;

                if ($days > AvailabilityService::MAX_DAYS) {
                    $validator->errors()->add('to', 'The range may not exceed '.AvailabilityService::MAX_DAYS.' days.');
                }
            },
        ];
    }
}
