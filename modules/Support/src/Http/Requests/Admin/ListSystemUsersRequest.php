<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;

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
