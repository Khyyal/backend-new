<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Support\Enums\SystemPermission;

class UpdateSystemRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('systemRole'));
    }

    /**
     * Partial update: only the fields sent change.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('system_roles', 'name')->ignore($this->route('systemRole'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            /** Permission names; replaces the role's permissions. */
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(SystemPermission::all())],
        ];
    }
}
