<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('update', $this->route('center'));
    }

    /**
     * Media fields take stable media ids (see the Support media API). `images`
     * is the full desired list, in display order: existing image ids are kept,
     * new temporary ids are attached, omitted images are removed.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'required', 'string', 'max:32'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}
