<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('city'));
    }

    /**
     * Partial update: only the fields sent change.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'array:en,ar'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'lat' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
