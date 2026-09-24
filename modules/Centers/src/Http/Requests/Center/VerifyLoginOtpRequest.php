<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLoginOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', 'exists:center_users,phone'],
            'code' => ['required', 'string', 'digits_between:4,6'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => __('validation.phone_required', ['default' => 'The phone number is required.']),
            'phone.string' => __('validation.phone_format', ['default' => 'The phone number format is invalid.']),
            'phone.max' => __('validation.phone_format', ['default' => 'The phone number format is invalid.']),
            'phone.exists' => __('auth.phone_not_registered_please_register', ['default' => 'This phone number is not registered. Please register first.']),
            'code.required' => __('validation.code_required', ['default' => 'The verification code is required.']),
            'code.string' => __('validation.code_digits', ['default' => 'The verification code must be between 4 and 6 digits.']),
            'code.digits_between' => __('validation.code_digits', ['default' => 'The verification code must be between 4 and 6 digits.']),
        ];
    }
}
