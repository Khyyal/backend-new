<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\QueryParam;

#[QueryParam('search', 'string', 'Name (any locale) or exact ID (max 100).', required: false, example: 'summer')]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100.', required: false, example: 15)]
class ItemCandidatesRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
