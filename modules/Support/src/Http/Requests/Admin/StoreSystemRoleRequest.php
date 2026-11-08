<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemRole;

class StoreSystemRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', SystemRole::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** snake_case, e.g. `support_agent`. */
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('system_roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            /** Permission names from `GET admin/system-permissions`. */
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(SystemPermission::all())],
        ];
    }
}
