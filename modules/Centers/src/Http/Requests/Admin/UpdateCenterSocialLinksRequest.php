<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Requests\Center\UpdateCenterSocialLinksRequest as CenterUpdateCenterSocialLinksRequest;

class UpdateCenterSocialLinksRequest extends CenterUpdateCenterSocialLinksRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('center'));
    }
}
