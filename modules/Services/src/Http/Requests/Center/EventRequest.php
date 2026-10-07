<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Modules\Services\Enums\EventOccurrenceType;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, description, occurrence_type, dates, max_tickets_per_day and
 * price_options); only the media fields are optional and are left untouched
 * when omitted.
 */
class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('manageServices', $this->route('center'));
    }

    /**
     * `occurrence_type` is informational (`specific_day` or `general`) and
     * does not change which fields are required: `start_date`, `end_date`,
     * `open_date` and `close_date` are always required (`end_date` must be on
     * or after `start_date`; `open_date`/`close_date` are otherwise
     * independent). `cover` and `images` take media ids from the Support
     * media API; `images` is the full desired list in display order.
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
            'occurrence_type' => ['required', new Enum(EventOccurrenceType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'open_date' => ['required', 'date'],
            'close_date' => ['required', 'date'],
            'max_tickets_per_day' => ['required', 'integer', 'min:1'],
            'price_options' => ['required', 'array', 'min:1', 'max:20'],
            'price_options.*.name' => ['required', 'string', 'max:255'],
            'price_options.*.price' => ['required', 'numeric', 'min:0'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}
