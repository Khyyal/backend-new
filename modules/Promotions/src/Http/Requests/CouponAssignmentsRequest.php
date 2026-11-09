<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('client_ids', 'integer[]', 'Distinct existing client IDs (1-1000).', example: [77, 78])]
class CouponAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'client_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'client_ids.*' => ['integer', 'distinct', 'exists:clients,id'],
        ];
    }
}
