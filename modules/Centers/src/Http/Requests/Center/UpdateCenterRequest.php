<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('update', $this->route('center'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * Media fields take stable media ids (see the Support media API). `images`
     * is the full desired list, in display order: existing image ids are kept,
     * new temporary ids are attached, omitted images are removed.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'required', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('centers', 'email')->ignore($this->route('center'))],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}
