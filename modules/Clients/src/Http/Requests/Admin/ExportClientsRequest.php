<?php

namespace Modules\Clients\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Modules\Clients\Models\Client;

/**
 * Same filters as the admin client list.
 */
class ExportClientsRequest extends ListClientsRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('export', Client::class);
    }
}
