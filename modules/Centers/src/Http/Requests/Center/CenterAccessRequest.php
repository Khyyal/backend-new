<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('password', 'string', 'Account password. Required for the primary (owner) user; ignored for other users.', required: false, nullable: true, example: 'secret-password')]
class CenterAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.string' => __('centers::validation.password_string'),
        ];
    }
}
