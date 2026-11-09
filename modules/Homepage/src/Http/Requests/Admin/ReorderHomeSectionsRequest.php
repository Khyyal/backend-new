<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('order', 'integer[]', 'Distinct existing section IDs in the new display order (1-200). Sections not listed keep their relative order after these.', example: [3, 1, 2])]
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
