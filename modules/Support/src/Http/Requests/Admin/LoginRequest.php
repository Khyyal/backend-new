<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('email', 'string', 'System user email (max 255).', example: 'sara@khyyal.com')]
#[BodyParam('password', 'string', 'Password (max 255).', example: 'secret-password')]
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
