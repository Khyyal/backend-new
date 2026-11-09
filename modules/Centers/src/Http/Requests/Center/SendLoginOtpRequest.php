<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('phone', 'string', 'Phone of a registered center user (max 32).', example: '+966500000002')]
class SendLoginOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', 'exists:center_users,phone'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => __('centers::validation.phone_required'),
            'phone.string' => __('centers::validation.phone_format'),
            'phone.max' => __('centers::validation.phone_format'),
            'phone.exists' => __('centers::auth.phone_not_registered_please_register'),
        ];
    }
}
