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
 * (name, description and price_options); `trainer_ids` and the media fields
 * are optional and are left untouched when omitted.
 */
class TrainingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', $this->route('center'));
    }

    /**
     * `trainer_ids` must be trainers of the same center. `cover` and `images`
     * take media ids from the Support media API; `images` is the full desired
     * list in display order.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'array'],
            'description.ar' => ['required', 'string'],
            'description.en' => ['nullable', 'string'],
            'price_options' => ['required', 'array', 'min:1', 'max:20'],
            'price_options.*.duration' => ['required', 'integer', 'min:1'],
            'price_options.*.price' => ['required', 'numeric', 'min:0'],
            'trainer_ids' => ['sometimes', 'array'],
            'trainer_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('trainers', 'id')
                    ->where('center_id', $center->getKey())
                    ->whereNull('deleted_at'),
            ],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
            ...TrainingService::settingsRules(),
            ...TrainingService::packageRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => TrainingService::validatePackageIndexes($validator, $this->all()),
        ];
    }
}
