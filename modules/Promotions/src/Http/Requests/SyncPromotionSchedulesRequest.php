<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('schedules', 'object[]', 'Full replacement list of weekly windows (max 50). Send `[]` to make the promotion live for its whole date range.', example: [['day_of_week' => 5, 'start_time' => '16:00', 'end_time' => '23:00']])]
#[BodyParam('schedules[].day_of_week', 'integer', 'ISO weekday: 1 = Monday … 7 = Sunday.', example: 5)]
#[BodyParam('schedules[].start_time', 'string', 'Start, `HH:MM` (UTC).', example: '16:00')]
#[BodyParam('schedules[].end_time', 'string', 'End, `HH:MM` (UTC), different from start. Earlier than start wraps past midnight.', example: '23:00')]
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
