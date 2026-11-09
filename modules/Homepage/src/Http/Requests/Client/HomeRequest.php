<?php

namespace Modules\Homepage\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\QueryParam;

#[QueryParam('lat', 'number', 'Your latitude; send with `lng`. Orders `top_centers` by distance and applies section radiuses.', required: false, example: 24.7)]
#[QueryParam('lng', 'number', 'Your longitude; send with `lat`.', required: false, example: 46.68)]
#[QueryParam('page', 'integer', 'Section endpoint only: page number (1-based).', required: false, example: 1)]
#[QueryParam('per_page', 'integer', 'Section endpoint only: items per page, 1-100 (default: the section limit).', required: false, example: 10)]
class HomeRequest extends FormRequest
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
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function latitude(): ?float
    {
        return $this->validated('lat') !== null ? (float) $this->validated('lat') : null;
    }

    public function longitude(): ?float
    {
        return $this->validated('lng') !== null ? (float) $this->validated('lng') : null;
    }
}
