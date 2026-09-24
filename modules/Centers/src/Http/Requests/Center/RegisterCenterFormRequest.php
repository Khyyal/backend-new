<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCenterFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'user.name' => ['required', 'string', 'max:255'],
            'user.phone' => ['required', 'string', 'max:32'],
            'user.code' => ['required', 'string'],
            'user.password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
