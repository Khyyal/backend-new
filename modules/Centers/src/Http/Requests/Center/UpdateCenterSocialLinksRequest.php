<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Enums\SocialPlatform;

class UpdateCenterSocialLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('update', $this->route('center'));
    }

    /**
     * `social_links` is keyed by platform; `null` clears all links.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'social_links' => ['present', 'nullable', 'array:'.implode(',', SocialPlatform::values())],
            'social_links.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
