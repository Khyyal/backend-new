<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;

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
            'password.string' => __('validation.password_string', ['default' => 'The password must be a string.']),
        ];
    }
}
