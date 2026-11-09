<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Models\Schedule;

/**
 * Replaces a center's gender periods (applied to all its services). `female` and `male` are each optional
 * (omit or `null` for none): `days` is a list of weekday numbers (0-6) and
 * `hours` a flat list of `start, end` pairs (`["09:00","12:00"]`) applied to
 * every chosen day.
 */
#[BodyParam('female', 'object', 'Female period; null clears it, omit to clear it too (the request replaces both periods).', required: false, nullable: true, example: ['days' => [2, 4], 'hours' => ['16:00', '20:00']])]
#[BodyParam('female.days', 'integer[]', 'Distinct weekdays (1-7 items), 0 = Sunday … 6 = Saturday. Required with `female`.', required: false, example: [2, 4])]
#[BodyParam('female.hours', 'string[]', '`HH:mm` start/end pairs (2-48 items), each end after its start. Required with `female`.', required: false, example: ['16:00', '20:00'])]
#[BodyParam('male', 'object', 'Male period; null clears it, omit to clear it too (the request replaces both periods).', required: false, nullable: true, example: ['days' => [2, 4], 'hours' => ['16:00', '20:00']])]
#[BodyParam('male.days', 'integer[]', 'Distinct weekdays (1-7 items), 0 = Sunday … 6 = Saturday. Required with `male`.', required: false, example: [2, 4])]
#[BodyParam('male.hours', 'string[]', '`HH:mm` start/end pairs (2-48 items), each end after its start. Required with `male`.', required: false, example: ['16:00', '20:00'])]
class CenterPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageAllServices', $this->route('center'));
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
                    Schedule::validateHourPairs($validator, $this->input("{$gender->value}.hours"), "{$gender->value}.hours");
                }
            },
        ];
    }
}
