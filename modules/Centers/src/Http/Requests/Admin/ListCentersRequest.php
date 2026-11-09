<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Models\Center;

#[QueryParam('search', 'string', 'Matches the center name or contact phone (contains).', required: false, example: 'Fursan')]
#[QueryParam('status', 'string', 'Only this visibility.', required: false, enum: CenterStatus::class, example: 'visible')]
#[QueryParam('state', 'string', 'Only this account state.', required: false, enum: CenterState::class, example: 'active')]
#[QueryParam('city_id', 'integer', 'Only centers of this city.', required: false, example: 1)]
#[QueryParam('from', 'string', 'Created on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Created on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
class ListCentersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', Center::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(CenterStatus::class)],
            'state' => ['sometimes', 'nullable', Rule::enum(CenterState::class)],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],
            /** Inclusive range on the center creation date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
