<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'unique:features,key'],
            'name' => ['required', 'string', 'max:255'],
            'is_quota' => ['nullable', 'boolean'],
        ];
    }
}
