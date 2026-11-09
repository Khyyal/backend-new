<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('key', 'string', 'Unique machine key (max 255).', required: false, example: 'custom_site')]
#[BodyParam('name', 'string', 'Display name (max 255).', required: false, example: 'Custom website')]
#[BodyParam('is_quota', 'boolean', 'Countable quota rather than a flag.', required: false, nullable: true, example: false)]
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
