<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
