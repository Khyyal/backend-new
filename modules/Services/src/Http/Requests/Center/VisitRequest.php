<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Visit;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, description, enter_type, max_tickets_per_day, price, days and hours); only the media
 * fields are optional and are left untouched when omitted.
 */
#[BodyParam('name', 'object', 'Name keyed by locale.', example: ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride'])]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', example: 'ركوب للمبتدئين')]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, nullable: true, example: 'Beginner ride')]
#[BodyParam('description', 'object', 'Description keyed by locale.', example: ['ar' => 'جولة بإشراف مدرب للمبتدئين.', 'en' => 'A guided ride for first-timers.'])]
#[BodyParam('description.ar', 'string', 'Arabic description.', example: 'جولة بإشراف مدرب للمبتدئين.')]
#[BodyParam('description.en', 'string', 'English description.', required: false, nullable: true, example: 'A guided ride for first-timers.')]
#[BodyParam('enter_type', 'string', '`all_day` or `specific_time`.', enum: VisitEnterType::class, example: 'specific_time')]
#[BodyParam('max_tickets_per_day', 'integer', 'Daily limit (min 1); null or omitted for none.', required: false, nullable: true, example: 100)]
#[BodyParam('price', 'integer', 'Ticket price in halalas (min 0).', example: 3000)]
#[BodyParam('days', 'integer[]', 'Distinct weekdays (1-7 items), Carbon numbering 0 = Sunday … 6 = Saturday.', example: [0, 1, 2, 3, 4])]
#[BodyParam('hours', 'string[]', 'Required when `enter_type` is `specific_time` (2-48 `HH:mm` start/end pairs applied to every day); prohibited for `all_day`.', required: false, example: ['09:00', '12:00', '16:00', '20:00'])]
#[BodyParam('cover', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or the current cover; null removes it; omit to keep it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('images', 'string[]', 'Full gallery in display order (max 10 distinct UUIDs): existing ones are kept, new temporary uploads attached, omitted ones removed. Omit to keep the gallery.', required: false, example: ['2d3e4f5a-6b7c-4d8e-9f0a-1b2c3d4e5f6a'])]
#[BodyParam('settings', 'object', 'Booleans keyed by `enable_cash_on_arrival`; other keys are rejected. On update replaces all settings (omitted keys become false); omit to leave them untouched.', required: false, example: ['enable_cash_on_arrival' => true])]
class VisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', [$this->route('center'), ServiceType::Visit]);
    }

    /**
     * `enter_type` is `all_day` or `specific_time`. `max_tickets_per_day` is an
     * optional integer >= 1; `null` or omitted means no daily limit. `hours` is a flat list of
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
            'max_tickets_per_day' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'integer', 'min:0'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
            'hours' => ['required_if:enter_type,specific_time', 'prohibited_if:enter_type,all_day', 'array', 'min:2', 'max:48'],
            'hours.*' => ['date_format:H:i'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
            ...Visit::settingsRules(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('enter_type') !== VisitEnterType::SpecificTime->value) {
                    return;
                }

                Schedule::validateHourPairs($validator, $this->input('hours'), 'hours');
            },
        ];
    }
}
