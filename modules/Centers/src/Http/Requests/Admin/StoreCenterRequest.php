<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Models\Center;

class StoreCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', Center::class);
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
     * Media fields take stable media ids (see the Support media API).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'description' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('centers', 'email')],
            'custom_site_enabled' => ['sometimes', 'boolean'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', Rule::unique('centers', 'domain')],
            'status' => ['sometimes', Rule::enum(CenterStatus::class)],
            'state' => ['sometimes', Rule::enum(CenterState::class)],
            'primary_user' => ['required', 'array:name,phone,password'],
            'primary_user.name' => ['required', 'string', 'max:255'],
            'primary_user.phone' => ['required', 'string', 'max:32', Rule::unique('center_users', 'phone')],
            'primary_user.password' => ['required', 'string', 'min:8', 'max:255'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}
