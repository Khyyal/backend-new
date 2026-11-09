<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('items', 'object[]', 'Features to attach or update (upsert, at least one).', example: [['feature_id' => 3, 'enabled' => true]])]
#[BodyParam('items[].feature_id', 'integer', 'Existing feature ID.', example: 3)]
#[BodyParam('items[].enabled', 'boolean', 'Enabled on the plan. Default true.', required: false, nullable: true, example: true)]
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
