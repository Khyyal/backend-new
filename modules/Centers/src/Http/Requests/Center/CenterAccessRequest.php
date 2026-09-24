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
            'password.string' => __('centers::validation.password_string'),
        ];
    }
}
