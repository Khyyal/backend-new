<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('items', 'object[]', 'Limits to attach or update (upsert, at least one).', example: [['limit_id' => 2, 'value' => 20]])]
#[BodyParam('items[].limit_id', 'integer', 'Existing limit ID.', example: 2)]
#[BodyParam('items[].value', 'integer', 'Limit value (min 0).', example: 20)]
class AttachLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.limit_id' => ['required', 'integer', 'exists:limits,id'],
            'items.*.value' => ['required', 'integer', 'gte:0'],
        ];
    }
}
