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
            'phone.required' => __('centers::validation.phone_required'),
            'phone.string' => __('centers::validation.phone_format'),
            'phone.max' => __('centers::validation.phone_format'),
            'phone.exists' => __('centers::auth.phone_not_registered_please_register'),
            'code.required' => __('centers::validation.code_required'),
            'code.string' => __('centers::validation.code_digits'),
            'code.digits_between' => __('centers::validation.code_digits'),
        ];
    }
}
