<?php

namespace Modules\Settlements\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Modules\Settlements\Models\CenterPayout;

/**
 * Same filters as the admin center ledger.
 */
class ExportCenterLedgerRequest extends ListCenterLedgerRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('export', CenterPayout::class);
    }
}
