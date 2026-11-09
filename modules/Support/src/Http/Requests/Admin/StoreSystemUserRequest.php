<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;

#[BodyParam('name', 'string', 'Display name (max 255).', example: 'Sara Admin')]
#[BodyParam('email', 'string', 'Unique login email (max 255).', example: 'sara@khyyal.com')]
#[BodyParam('password', 'string', 'Password, min 8 characters.', example: 'secret-password')]
#[BodyParam('password_confirmation', 'string', 'Must equal `password`.', example: 'secret-password')]
#[BodyParam('status', 'string', 'Default `active`.', required: false, enum: ActivationStatus::class, example: 'active')]
#[BodyParam('roles', 'string[]', 'Distinct existing role names. Requires `system_users.assign_roles`; only super admins grant `super_admin`.', required: false, example: ['support_agent'])]
class StoreSystemUserRequest extends FormRequest
{
    /**
     * Sending `roles` also needs the `system_users.assign_roles` permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return Gate::forUser($user)->allows('create', SystemUser::class)
            && (! $this->has('roles') || $user->hasPermissionTo(SystemPermission::AssignSystemUserRoles));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('system_users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'status' => ['sometimes', Rule::enum(ActivationStatus::class)],
            /** Role names to assign. */
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('system_roles', 'name')],
        ];
    }
}
