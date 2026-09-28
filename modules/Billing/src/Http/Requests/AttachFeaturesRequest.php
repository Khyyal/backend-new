<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.feature_id' => ['required', 'integer', 'exists:features,id'],
            'items.*.enabled' => ['nullable', 'boolean'],
        ];
    }
}
