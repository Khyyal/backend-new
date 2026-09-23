<?php

namespace Modules\Clients\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:255'],
            'last_name' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => __('validation.name_required'),
            'first_name.string' => __('validation.name_min'),
            'first_name.min' => __('validation.name_min'),
            'first_name.max' => __('validation.name_max'),

            'last_name.required' => __('validation.name_required'),
            'last_name.string' => __('validation.name_min'),
            'last_name.min' => __('validation.name_min'),
            'last_name.max' => __('validation.name_max'),
        ];
    }
}
