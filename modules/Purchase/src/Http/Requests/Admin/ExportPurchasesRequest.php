<?php

namespace Modules\Purchase\Http\Requests\Admin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Modules\Purchase\Models\Purchase;

/**
 * Same filters as the admin purchase list.
 */
class ExportPurchasesRequest extends ListPurchasesRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('export', Purchase::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['per_page']);
    }
}
