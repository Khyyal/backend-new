<?php

namespace Modules\Clients\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\AdminClientQuery;
use Modules\Support\Enums\Gender;

#[QueryParam('search', 'string', 'Matches the full name (e.g. `Aisha Noor`) or phone; every word must match (max 100).', required: false, example: 'Noura')]
#[QueryParam('city_id', 'integer', 'Only clients of this city.', required: false, example: 1)]
#[QueryParam('gender', 'string', 'Only this gender.', required: false, enum: Gender::class, example: 'female')]
#[QueryParam('from', 'string', 'Registered on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Registered on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
#[QueryParam('sort', 'string', 'Sort column; prefix `-` for descending. Default newest first.', required: false, enum: AdminClientQuery::SORTS, example: 'name')]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
class ListClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', Client::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Matches the full name (e.g. `Aisha Noor`) or the phone number; every word must match. */
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            /** Only clients of this city. */
            'city_id' => ['sometimes', 'nullable', 'integer'],
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
            /** Inclusive range on the registration date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['sometimes', 'nullable', Rule::in(AdminClientQuery::SORTS)],
        ];
    }
}
