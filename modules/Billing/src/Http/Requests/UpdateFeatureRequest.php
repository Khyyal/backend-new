<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $featureId = $this->route('feature')?->id ?? $this->route('feature');

        return [
            'key' => ['nullable', 'string', 'max:255', Rule::unique('features', 'key')->ignore($featureId)],
            'name' => ['nullable', 'string', 'max:255'],
            'is_quota' => ['nullable', 'boolean'],
        ];
    }
}
