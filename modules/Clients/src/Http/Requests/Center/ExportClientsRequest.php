<?php

namespace Modules\Clients\Http\Requests\Center;

use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * Same filters as the center client list.
 */
#[BodyParam('q', 'string', 'Matches the full name or phone; every word must match (max 100).', required: false, example: 'Noura')]
#[BodyParam('from', 'string', 'Last purchase on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[BodyParam('to', 'string', 'Last purchase on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
class ExportClientsRequest extends ListClientsRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('exportClients', $this->center());
    }
}
