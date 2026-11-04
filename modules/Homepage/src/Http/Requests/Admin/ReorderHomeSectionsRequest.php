<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderHomeSectionsRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1', 'max:200'],
            'order.*' => ['integer', 'distinct', 'exists:home_sections,id'],
        ];
    }
}
