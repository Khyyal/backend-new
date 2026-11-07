<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Requests\Center\UpdateCenterSiteThemeRequest as CenterUpdateCenterSiteThemeRequest;

class UpdateCenterSiteThemeRequest extends CenterUpdateCenterSiteThemeRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('center'));
    }
}
