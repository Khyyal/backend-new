<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Support\Enums\DevicePlatform;

class RegisterCenterFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('centers', 'email')],
            'user.name' => ['required', 'string', 'max:255'],
            'user.phone' => ['required', 'string', 'max:32'],
            'user.code' => ['required', 'string'],
            'user.password' => ['required', 'string', 'min:8', 'max:255'],
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', Rule::enum(DevicePlatform::class)],
        ];
    }
}
