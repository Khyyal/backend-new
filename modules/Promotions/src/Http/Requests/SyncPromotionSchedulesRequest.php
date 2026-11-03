<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPromotionSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'schedules' => ['present', 'array', 'max:50'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'different:schedules.*.start_time'],
        ];
    }
}
