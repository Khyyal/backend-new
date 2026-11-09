<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Enums\DevicePlatform;

#[BodyParam('phone', 'string', 'Same phone used on `POST /api/v1/centers/auth/phone`.', example: '+966500000002')]
#[BodyParam('code', 'string', 'OTP, 4-6 digits.', example: '1234')]
#[BodyParam('device_identifier', 'string', 'Device ID sent to the launch endpoint, to link the device (max 255). Can also be sent as the `X-Device-Identifier` header.', required: false, nullable: true, example: 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6')]
#[BodyParam('platform', 'string', 'Same platform sent to the launch endpoint.', required: false, nullable: true, enum: DevicePlatform::class, example: 'ios')]
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
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', Rule::enum(DevicePlatform::class)],
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
