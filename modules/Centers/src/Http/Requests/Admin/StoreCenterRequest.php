<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\SiteThemeColor;
use Modules\Centers\Enums\SocialPlatform;
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
            'social_links' => ['sometimes', 'nullable', 'array:'.implode(',', SocialPlatform::values())],
            'social_links.*' => ['nullable', 'string', 'max:255'],
            'site_theme' => ['sometimes', 'nullable', 'array:'.implode(',', SiteThemeColor::values())],
            'site_theme.*' => ['nullable', 'string', 'hex_color'],
            'custom_site_enabled' => ['sometimes', 'boolean'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', Rule::unique('centers', 'domain')],
            'status' => ['sometimes', Rule::enum(CenterStatus::class)],
            'state' => ['sometimes', Rule::enum(CenterState::class)],
            'primary_user_id' => ['nullable', 'integer', Rule::exists('center_users', 'id')->whereNull('deleted_at')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}
