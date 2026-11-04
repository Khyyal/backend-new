<?php

namespace Modules\Homepage\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

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
