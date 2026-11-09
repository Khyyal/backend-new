<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemRole;

#[BodyParam('name', 'string', 'Unique snake_case name: starts with a letter, then a-z, 0-9, `_` (max 64).', required: true, example: 'support_agent')]
#[BodyParam('description', 'string', 'Free-text description (max 255).', required: false, nullable: true, example: 'Handles client support tickets.')]
#[BodyParam('permissions', 'string[]', 'Distinct permission names (see `GET /api/v1/admin/system-permissions`). Replaces the current set; you can only grant permissions you hold.', required: false, example: ['centers.view_any', 'centers.view'])]
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
