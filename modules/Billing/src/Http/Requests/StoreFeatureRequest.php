<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('key', 'string', 'Unique machine key (max 255).', required: true, example: 'custom_site')]
#[BodyParam('name', 'string', 'Display name (max 255).', required: true, example: 'Custom website')]
#[BodyParam('is_quota', 'boolean', 'Countable quota rather than a flag. Default false.', required: false, nullable: true, example: false)]
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
