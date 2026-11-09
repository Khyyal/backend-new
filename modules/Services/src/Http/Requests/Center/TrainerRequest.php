<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\TrainingService;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, max_tickets_per_hour, days and hours); `training_service_ids` is
 * optional and left untouched when omitted.
 */
#[BodyParam('name', 'string', 'Trainer name (max 255).', example: 'Fahad')]
#[BodyParam('max_tickets_per_hour', 'integer', 'Trainees per hour (min 1); null or omitted for no limit.', required: false, nullable: true, example: 2)]
#[BodyParam('max_tickets_per_hour_by_day', 'object', 'Per-weekday overrides (weekday from `days` => limit, min 1, max 7 entries); null or omitted for none.', required: false, nullable: true, example: ['5' => 4, '6' => 3])]
#[BodyParam('days', 'integer[]', 'Distinct weekdays (1-7 items), Carbon numbering 0 = Sunday … 6 = Saturday.', example: [0, 1, 2, 3, 4])]
#[BodyParam('hours', 'string[]', 'Flat list of `HH:mm` start/end pairs (2-48 items) applied to every day in `days`; each end after its start.', required: true, example: ['09:00', '12:00', '16:00', '20:00'])]
#[BodyParam('training_service_ids', 'integer[]', 'Full list of distinct training service IDs of this center the trainer teaches; omit to leave untouched.', required: false, example: [31])]
class TrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', [$this->route('center'), ServiceType::Training]);
    }

    /**
     * `max_tickets_per_hour` is an optional integer >= 1; `null` or omitted means
     * no hourly limit. `max_tickets_per_hour_by_day` optionally overrides it on
     * some days: a map of weekday (one of `days`) to an integer >= 1, e.g. `{"5": 10}`.
     * `hours` is a flat list of `start, end` pairs (`["09:00","12:00","14:00","17:00"]`)
     * applied to every day in `days` (0-6). `training_service_ids` must be
     * training services of the same center.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center|null $center */
        $center = $this->route('center');

        return [
            'name' => ['required', 'string', 'max:255'],
            'max_tickets_per_hour' => ['nullable', 'integer', 'min:1'],
            'max_tickets_per_hour_by_day' => ['nullable', 'array', 'max:7'],
            'max_tickets_per_hour_by_day.*' => ['integer', 'min:1'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
            'hours' => ['required', 'array', 'min:2', 'max:48'],
            'hours.*' => ['date_format:H:i'],
            'training_service_ids' => ['sometimes', 'array'],
            'training_service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'serviceable_id')
                    ->where('serviceable_type', (new TrainingService)->getMorphClass())
                    ->where('center_id', $center?->getKey())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                Schedule::validateHourPairs($validator, $this->input('hours'), 'hours');
            },
            function (Validator $validator): void {
                $overrides = $this->input('max_tickets_per_hour_by_day');
                $days = $this->input('days');

                if (! is_array($overrides) || ! is_array($days) || $validator->errors()->has('days.*')) {
                    return;
                }

                foreach (array_keys($overrides) as $day) {
                    if (! in_array((string) $day, array_map('strval', $days), true)) {
                        $validator->errors()->add("max_tickets_per_hour_by_day.$day", 'Each overridden day must be one of the trainer days.');
                    }
                }
            },
        ];
    }
}
