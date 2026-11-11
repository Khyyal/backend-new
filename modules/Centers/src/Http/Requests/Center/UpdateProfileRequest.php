<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('name', 'string', 'Full name (max 255).', required: false, example: 'Khalid Al-Harbi')]
#[BodyParam('password', 'string', 'New access password (min 8). Send `password_confirmation` too.', required: false, example: 'new-secret-123')]
#[BodyParam('password_confirmation', 'string', 'Must match `password`.', required: false, example: 'new-secret-123')]
#[BodyParam('current_password', 'string', 'Current password. Required when changing a password that is already set.', required: false, example: 'old-secret-123')]
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('update', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->filled('password') && $this->user('center_user')->password !== null),
                'nullable',
                'string',
                'current_password:center_user',
            ],
        ];
    }
}
