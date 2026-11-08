<?php

namespace Modules\Clients\Http\Requests\Center;

use Illuminate\Support\Facades\Gate;

/**
 * Same filters as the center client list.
 */
class ExportClientsRequest extends ListClientsRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('exportClients', $this->center());
    }
}
