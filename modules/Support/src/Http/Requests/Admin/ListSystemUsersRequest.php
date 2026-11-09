<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;

#[QueryParam('search', 'string', 'Matches name or email (contains).', required: false, example: 'sara')]
#[QueryParam('status', 'string', 'Only this status.', required: false, enum: ActivationStatus::class, example: 'active')]
#[QueryParam('role', 'string', 'Only users holding this role name.', required: false, example: 'support_agent')]
#[QueryParam('trashed', 'string', '`with` includes deleted users; `only` lists only deleted users.', required: false, enum: ['with', 'only'], example: 'with')]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
class ListSystemUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', SystemUser::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Matches the name or email. */
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ActivationStatus::class)],
            /** Role name. */
            'role' => ['sometimes', 'string', 'max:255'],
            /** `with` includes deleted users, `only` lists just them. */
            'trashed' => ['sometimes', Rule::in(['with', 'only'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
