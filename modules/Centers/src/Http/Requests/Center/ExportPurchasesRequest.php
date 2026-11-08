<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Support\Facades\Gate;

/**
 * Same filters as the center purchase list.
 */
class ExportPurchasesRequest extends ListPurchasesRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('exportPurchases', $this->center());
    }
}
