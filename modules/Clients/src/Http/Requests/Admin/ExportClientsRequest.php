<?php

namespace Modules\Clients\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\AdminClientQuery;
use Modules\Support\Enums\Gender;

/**
 * Same filters as the admin client list.
 */
#[BodyParam('search', 'string', 'Matches the full name (e.g. `Aisha Noor`) or phone; every word must match (max 100).', required: false, example: 'Noura')]
#[BodyParam('city_id', 'integer', 'Only clients of this city.', required: false, example: 1)]
#[BodyParam('gender', 'string', 'Only this gender.', required: false, enum: Gender::class, example: 'female')]
#[BodyParam('from', 'string', 'Registered on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[BodyParam('to', 'string', 'Registered on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
#[BodyParam('sort', 'string', 'Row order, as in the list; prefix `-` for descending. Default newest first.', required: false, enum: AdminClientQuery::SORTS, example: 'name')]
class ExportClientsRequest extends ListClientsRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('export', Client::class);
    }
}
