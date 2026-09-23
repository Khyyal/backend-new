<?php

namespace Modules\Clients\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9]{6,20}$/'],
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.required' => __('validation.phone_required'),
            'phone_number.string' => __('validation.phone_format'),
            'phone_number.max' => __('validation.phone_format'),
            'phone_number.regex' => __('validation.phone_format'),
            'code.required' => __('validation.code_required'),
            'code.digits' => __('validation.code_digits'),
            'device_identifier.required' => __('validation.device_identifier_required'),
            'device_identifier.string' => __('validation.device_identifier_format'),
            'device_identifier.min' => __('validation.device_identifier_min'),
            'device_identifier.max' => __('validation.device_identifier_max'),
        ];
    }
}
