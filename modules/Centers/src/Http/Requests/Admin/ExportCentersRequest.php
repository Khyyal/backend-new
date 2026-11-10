<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAdminService;

/**
 * Same filters as the admin center list.
 */
#[BodyParam('search', 'string', 'Matches the center name or contact phone (contains).', required: false, example: 'Fursan')]
#[BodyParam('status', 'string', 'Only this visibility.', required: false, enum: CenterStatus::class, example: 'visible')]
#[BodyParam('state', 'string', 'Only this account state.', required: false, enum: CenterState::class, example: 'active')]
#[BodyParam('city_id', 'integer', 'Only centers of this city.', required: false, example: 1)]
#[BodyParam('from', 'string', 'Created on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[BodyParam('to', 'string', 'Created on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
#[BodyParam('sort', 'string', 'Row order, as in the list; prefix `-` for descending. Default newest first.', required: false, enum: CenterAdminService::SORTS, example: 'name')]
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
