<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Services\Enums\BlockTimeScope;

/**
 * Body shared by create and update. `service_id` omitted or `null` blocks
 * all of the center's services; otherwise it must be a service belonging
 * to the center. A single-date block is `start_date` equal to `end_date`.
 * `hours` is a flat list of `start, end` pairs (`["12:00","13:00","19:00","20:00"]`
 * for two separate periods); required when `time_scope` is `specific_time`
 * and must be absent when it is `all_day`.
 */
class ServiceBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('manageServices', $this->route('center'));
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('center_id', $this->route('center')->id)],
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

                $hours = $this->input('hours');

                if (! is_array($hours) || $validator->errors()->has('hours.*') || $validator->errors()->has('hours')) {
                    return;
                }

                if (count($hours) % 2 !== 0) {
                    $validator->errors()->add('hours', 'hours must contain start and end pairs.');

                    return;
                }

                foreach (array_chunk($hours, 2) as $index => [$start, $end]) {
                    if ($start >= $end) {
                        $validator->errors()->add('hours.'.($index * 2 + 1), 'Each end time must be after its start time.');
                    }
                }
            },
        ];
    }
}
