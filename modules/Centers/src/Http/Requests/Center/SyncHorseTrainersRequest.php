<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Models\Center;

/**
 * The full list of trainers working with a horse; replaces the current one.
 */
#[BodyParam('trainer_ids', 'integer[]', 'Every trainer of the horse (max 50, trainers of this center). Missing trainers are unlinked; `[]` unlinks all.', example: [6, 9])]
class SyncHorseTrainersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageHorses', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'trainer_ids' => ['present', 'array', 'max:50'],
            'trainer_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('trainers', 'id')
                    ->where('center_id', $center->getKey())
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
