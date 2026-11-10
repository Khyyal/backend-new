<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;

#[BodyParam('name', 'string', 'Full name (max 255).', required: false, example: 'Fahad Al-Otaibi')]
#[BodyParam('phone', 'string', 'Phone (max 32), unique across center users. A new phone must be verified again and signs the account out everywhere.', required: false, example: '+966500000004')]
#[BodyParam('trainer_id', 'integer', 'Trainer of this center (not deleted) not already linked to another user of the center. Not for the primary user.', required: false, example: 6)]
#[BodyParam('status', 'string', 'Assignment status. Not for the primary user.', required: false, enum: ActivationStatus::class, example: 'active')]
#[BodyParam('permissions', 'string[]', 'Full list of distinct center permissions (see `GET …/users/permissions`). Not for the primary user.', required: false, enum: CenterUserPermission::class, example: ['services.training.manage', 'purchases.training.view'])]
class UpdateCenterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('system_user'))->allows('manageUsersAsAdmin', $this->route('center'));
    }

    /**
     * `phone` must be free across center users (soft-deleted included);
     * `trainer_id` must be a trainer of the center not linked to another
     * user of the center.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center|null $center */
        $center = $this->route('center');
        $userId = (int) $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:32',
                Rule::unique((new User)->getTable(), 'phone')->ignore($userId),
            ],
            'trainer_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('trainers', 'id')
                    ->where('center_id', $center?->getKey())
                    ->whereNull('deleted_at'),
                Rule::unique('center_user_assignment', 'trainer_id')
                    ->where('center_id', $center?->getKey())
                    ->whereNot('user_id', $userId),
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
            'phone.unique' => __('centers::validation.phone_taken'),
            'trainer_id.exists' => __('centers::validation.trainer_invalid'),
            'trainer_id.unique' => __('centers::validation.trainer_already_linked'),
        ];
    }
}
