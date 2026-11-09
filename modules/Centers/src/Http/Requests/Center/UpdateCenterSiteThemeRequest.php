<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Enums\SiteThemeColor;

#[BodyParam('site_theme', 'object', 'Hex colors (`#RRGGBB`) keyed by role (`background`, `foreground`, `primary`, `button`, `secondary`, `accent`), each nullable. Replaces the theme; send null to clear.', nullable: true, example: ['primary' => '#8B4513', 'background' => '#FFFFFF'])]
class UpdateCenterSiteThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('update', $this->route('center'));
    }

    /**
     * `site_theme` is keyed by color role with hex colors; `null` clears the theme.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'site_theme' => ['present', 'nullable', 'array:'.implode(',', SiteThemeColor::values())],
            'site_theme.*' => ['nullable', 'string', 'hex_color'],
        ];
    }
}
