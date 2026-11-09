<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;

class UpdateCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('center'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge(['domain' => strtolower(trim($this->input('domain')))]);
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * Partial update. `images` is the full desired list in display order.
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
            'custom_site_enabled' => ['sometimes', 'boolean'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', Rule::unique('centers', 'domain')->ignore($this->route('center'))],
            'status' => ['sometimes', 'required', Rule::enum(CenterStatus::class)],
            'state' => ['sometimes', 'required', Rule::enum(CenterState::class)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}
