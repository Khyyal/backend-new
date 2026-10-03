<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limitId = $this->route('limit')?->id ?? $this->route('limit');

        return [
            'key' => ['nullable', 'string', 'max:255', Rule::unique('limits', 'key')->ignore($limitId)],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
