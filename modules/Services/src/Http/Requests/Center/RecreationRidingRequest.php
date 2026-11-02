<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Schedule;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, description, max_tickets_per_hour, price options, days and hours); only the media fields
 * are optional and are left untouched when omitted.
 */
class RecreationRidingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', [$this->route('center'), ServiceType::RecreationRiding]);
    }

    /**
     * `max_tickets_per_hour` is an optional integer >= 1; `null` or omitted means
     * no hourly limit. `hours` is a flat list of `start, end` pairs (`["09:00","12:00","14:00","17:00"]`)
     * applied to every day in `days` (0-6). `cover` and `images` take media ids
     * from the Support media API; `images` is the full desired list in display order.
     *
     * @return array<string, array<int, string>>
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
            'max_tickets_per_hour' => ['nullable', 'integer', 'min:1'],
            'price_options' => ['required', 'array', 'min:1', 'max:20'],
            'price_options.*.duration' => ['required', 'integer', 'min:1'],
            'price_options.*.price' => ['required', 'numeric', 'min:0'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
            'hours' => ['required', 'array', 'min:2', 'max:48'],
            'hours.*' => ['date_format:H:i'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
            ...RecreationalRiding::settingsRules(),
            ...RecreationalRiding::packageRules(),
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => RecreationalRiding::validatePackageIndexes($validator, $this->all()),
            function (Validator $validator): void {
                Schedule::validateHourPairs($validator, $this->input('hours'), 'hours');
            },
        ];
    }
}
