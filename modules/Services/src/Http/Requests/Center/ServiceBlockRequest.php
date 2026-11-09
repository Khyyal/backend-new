<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Services\Enums\BlockTimeScope;
use Modules\Services\Models\Schedule;

/**
 * Body shared by create and update. `service_id` omitted or `null` blocks
 * all of the center's services; otherwise it must be a service belonging
 * to the center. A single-date block is `start_date` equal to `end_date`.
 * `hours` is a flat list of `start, end` pairs (`["12:00","13:00","19:00","20:00"]`
 * for two separate periods); required when `time_scope` is `specific_time`
 * and must be absent when it is `all_day`.
 */
#[BodyParam('service_id', 'integer', 'A service of this center; null or omitted blocks every service.', required: false, nullable: true, example: 31)]
#[BodyParam('reason', 'string', 'Reason (max 500).', example: 'Maintenance')]
#[BodyParam('start_date', 'string', 'First blocked day (date).', example: '2026-06-10')]
#[BodyParam('end_date', 'string', 'Last blocked day, on or after `start_date` (equal for one day).', example: '2026-06-11')]
#[BodyParam('time_scope', 'string', '`all_day` or `specific_time`.', enum: BlockTimeScope::class, example: 'specific_time')]
#[BodyParam('hours', 'string[]', 'Required for `specific_time`, prohibited for `all_day`: `HH:mm` start/end pairs (2-48 items) blocked each day.', required: false, example: ['09:00', '12:00'])]
class ServiceBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', $this->route('center'));
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('center_id', $this->route('center')?->id)],
            'reason' => ['required', 'string', 'max:500'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'time_scope' => ['required', new Enum(BlockTimeScope::class)],
            'hours' => ['required_if:time_scope,specific_time', 'prohibited_if:time_scope,all_day', 'array', 'min:2', 'max:48'],
            'hours.*' => ['date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('time_scope') !== BlockTimeScope::SpecificTime->value) {
                    return;
                }

                Schedule::validateHourPairs($validator, $this->input('hours'), 'hours');
            },
        ];
    }
}
