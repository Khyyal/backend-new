<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('phone', 'string', 'Phone not yet registered as a center user (max 32).', example: '+966500000002')]
class SendRegisterOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', 'unique:center_users,phone'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => __('centers::validation.phone_required'),
            'phone.string' => __('centers::validation.phone_format'),
            'phone.max' => __('centers::validation.phone_format'),
            'phone.unique' => __('centers::auth.phone_already_registered_please_login'),
        ];
    }
}
