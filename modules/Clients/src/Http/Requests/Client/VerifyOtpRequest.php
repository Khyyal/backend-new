<?php

namespace Modules\Clients\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Enums\DevicePlatform;

#[BodyParam('phone_number', 'string', 'Same phone used on `POST /api/v1/clients/auth/login`, in international format: optional `+` then 6-20 digits (max 30).', example: '+966500000003')]
#[BodyParam('code', 'string', 'The 4-digit code received by SMS.', example: '1234')]
#[BodyParam('device_identifier', 'string', 'Device ID sent to `POST /api/v1/clients/launch`, to link the device (max 255). Can also be sent as the `X-Device-Identifier` header.', required: false, nullable: true, example: 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6')]
#[BodyParam('platform', 'string', 'Same platform sent to the launch endpoint.', required: false, nullable: true, enum: DevicePlatform::class, example: 'android')]
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
            'code' => ['required', 'digits:4'],
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', Rule::enum(DevicePlatform::class)],
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
