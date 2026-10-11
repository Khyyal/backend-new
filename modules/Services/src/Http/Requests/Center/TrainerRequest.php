<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Centers\Models\Center;
use Modules\Services\Models\TrainingService;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, max_tickets_per_hour, days and hours); `training_service_ids` is
 * optional and left untouched when omitted.
 */
class TrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', $this->route('center'));
    }

    /**
     * `max_tickets_per_hour` is an optional integer >= 1; `null` or omitted means
     * no hourly limit. `hours` is a flat list of `start, end` pairs (`["09:00","12:00","14:00","17:00"]`)
     * applied to every day in `days` (0-6). `training_service_ids` must be
     * training services of the same center.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'name' => ['required', 'string', 'max:255'],
            'max_tickets_per_hour' => ['nullable', 'integer', 'min:1'],
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
                    ->where('center_id', $center->getKey())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
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
