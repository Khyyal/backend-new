<?php

namespace Modules\Clients\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class LoginOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9]{6,20}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.required' => __('validation.phone_required'),
            'phone_number.string' => __('validation.phone_format'),
            'phone_number.max' => __('validation.phone_format'),
            'phone_number.regex' => __('validation.phone_format'),
        ];
    }
}
