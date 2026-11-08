<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Models\Center;

/**
 * Same filters as the admin center list.
 */
class ExportCentersRequest extends ListCentersRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('export', Center::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['per_page']);
    }
}
