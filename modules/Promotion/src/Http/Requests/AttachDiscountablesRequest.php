<?php

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachDiscountablesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.discountable_type' => ['required', 'string', 'max:255'],
            'items.*.discountable_id' => ['required'],
        ];
    }
}
