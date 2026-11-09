<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Support\Enums\ActivationStatus;

#[BodyParam('name', 'string', 'Full name (max 255). Kept as-is when the phone already has an account.', required: false, example: 'Fahad Al-Otaibi')]
#[BodyParam('trainer_id', 'integer', 'Trainer of this center (not deleted) not already linked to another user of the center.', required: false, example: 6)]
#[BodyParam('status', 'string', 'Assignment status. Default `active`.', required: false, enum: ActivationStatus::class, example: 'active')]
#[BodyParam('permissions', 'string[]', 'Full list of distinct center permissions (see `GET …/users/permissions`); a non-primary manager can only grant permissions they hold.', required: false, enum: CenterUserPermission::class, example: ['services.training.manage', 'purchases.training.view'])]
class UpdateCenterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('manageUsers', $this->route('center'));
    }

    /**
     * `trainer_id` must be a trainer of the center that is not linked to
     * another user of the center.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center|null $center */
        $center = $this->route('center');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trainer_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('trainers', 'id')
                    ->where('center_id', $center?->getKey())
                    ->whereNull('deleted_at'),
                Rule::unique('center_user_assignment', 'trainer_id')
                    ->where('center_id', $center?->getKey())
                    ->whereNot('user_id', (int) $this->route('user')),
            ],
            'status' => ['sometimes', Rule::enum(ActivationStatus::class)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(CenterUserPermission::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trainer_id.exists' => __('centers::validation.trainer_invalid'),
            'trainer_id.unique' => __('centers::validation.trainer_already_linked'),
        ];
    }
}
