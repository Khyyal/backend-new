<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Services\Enums\PeriodGender;

/**
 * Replaces a service's gender periods. `female` and `male` are each optional
 * (omit or `null` for none): `days` is a list of weekday numbers (0-6) and
 * `hours` a flat list of `start, end` pairs (`["09:00","12:00"]`) applied to
 * every chosen day.
 */
class ServicePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', $this->route('center'));
    }

    public function rules(): array
    {
        $rules = [];

        foreach (PeriodGender::cases() as $gender) {
            $key = $gender->value;

            $rules[$key] = ['nullable', 'array:days,hours'];
            $rules["$key.days"] = ['required_with:'.$key, 'array', 'min:1', 'max:7'];
            $rules["$key.days.*"] = ['integer', 'between:0,6', 'distinct'];
            $rules["$key.hours"] = ['required_with:'.$key, 'array', 'min:2', 'max:48'];
            $rules["$key.hours.*"] = ['date_format:H:i'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (PeriodGender::cases() as $gender) {
                    $key = $gender->value;
                    $hours = $this->input("$key.hours");

                    if (! is_array($hours) || $validator->errors()->has("$key.hours") || $validator->errors()->has("$key.hours.*")) {
                        continue;
                    }

                    if (count($hours) % 2 !== 0) {
                        $validator->errors()->add("$key.hours", 'hours must contain start and end pairs.');

                        continue;
                    }

                    foreach (array_chunk($hours, 2) as $index => [$start, $end]) {
                        if ($start >= $end) {
                            $validator->errors()->add("$key.hours.".($index * 2 + 1), 'Each end time must be after its start time.');
                        }
                    }
                }
            },
        ];
    }
}
