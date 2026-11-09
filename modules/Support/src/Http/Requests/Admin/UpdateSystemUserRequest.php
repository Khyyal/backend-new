<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;

#[BodyParam('name', 'string', 'Display name (max 255).', required: false, example: 'Sara Admin')]
#[BodyParam('email', 'string', 'Unique login email (max 255).', required: false, example: 'sara@khyyal.com')]
#[BodyParam('password', 'string', 'New password, min 8 characters. Revokes the user\'s tokens.', required: false, example: 'new-secret-password')]
#[BodyParam('password_confirmation', 'string', 'Required with `password`; must equal it.', required: false, example: 'new-secret-password')]
#[BodyParam('status', 'string', '`inactive` revokes the user\'s tokens. You cannot change your own status.', required: false, enum: ActivationStatus::class, example: 'inactive')]
#[BodyParam('roles', 'string[]', 'Replaces the user\'s roles. Requires `system_users.assign_roles`; you cannot change your own roles.', required: false, example: ['support_agent'])]
class UpdateSystemUserRequest extends FormRequest
{
    /**
     * Sending `roles` also needs the `assignRoles` ability; nobody changes their own status.
     */
    public function authorize(): bool
    {
        $gate = Gate::forUser($this->user());
        $target = $this->targetUser();

        return $gate->allows('update', $target)
            && (! $this->has('roles') || $gate->allows('assignRoles', $target))
            && (! $this->has('status') || ! $this->user()->is($target));
    }

    /**
     * Partial update: only the fields sent change.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('system_users', 'email')->ignore($this->targetUser())],
            'password' => ['sometimes', 'required', 'string', Password::min(8), 'confirmed'],
            'status' => ['sometimes', 'required', Rule::enum(ActivationStatus::class)],
            /** Role names; replaces the user's roles. */
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('system_roles', 'name')],
        ];
    }

    private function targetUser(): SystemUser
    {
        return $this->route('systemUser');
    }
}
