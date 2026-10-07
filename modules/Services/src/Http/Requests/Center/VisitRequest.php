<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Modules\Services\Enums\VisitEnterType;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, description, enter_type, price, days and hours); only the media
 * fields are optional and are left untouched when omitted.
 */
class VisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('manageServices', $this->route('center'));
    }

    /**
     * `enter_type` is `all_day` or `specific_time`. `hours` is a flat list of
     * `start, end` pairs (`["09:00","12:00","14:00","17:00"]`) applied to every
     * day in `days` (0-6); required when `enter_type` is `specific_time` and
     * must be absent when it is `all_day`. `cover` and `images` take media ids
     * from the Support media API; `images` is the full desired list in display
     * order.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'array'],
            'description.ar' => ['required', 'string'],
            'description.en' => ['nullable', 'string'],
            'enter_type' => ['required', new Enum(VisitEnterType::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
            'hours' => ['required_if:enter_type,specific_time', 'prohibited_if:enter_type,all_day', 'array', 'min:2', 'max:48'],
            'hours.*' => ['date_format:H:i'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('enter_type') !== VisitEnterType::SpecificTime->value) {
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
