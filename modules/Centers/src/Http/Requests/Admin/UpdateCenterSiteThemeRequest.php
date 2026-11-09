<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Http\Requests\Center\UpdateCenterSiteThemeRequest as CenterUpdateCenterSiteThemeRequest;

#[BodyParam('site_theme', 'object', 'Hex colors (`#RRGGBB`) keyed by role (`background`, `foreground`, `primary`, `button`, `secondary`, `accent`), each nullable. Replaces the theme; send null to clear.', nullable: true, example: ['primary' => '#8B4513', 'background' => '#FFFFFF'])]
class UpdateCenterSiteThemeRequest extends CenterUpdateCenterSiteThemeRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('center'));
    }
}
