<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Purchase\Enums\PurchaseStatus;

/**
 * Same filters as the center purchase list.
 */
#[BodyParam('status', 'string', 'Only this purchase status.', required: false, enum: PurchaseStatus::class, example: 'confirmed')]
#[BodyParam('q', 'string', 'Part of the client\'s full name or phone (max 100).', required: false, example: 'Noura')]
#[BodyParam('service_id', 'integer', 'Only purchases with a reservation or package of this service.', required: false, example: 31)]
#[BodyParam('from', 'string', 'Created on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[BodyParam('to', 'string', 'Created on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
class ExportPurchasesRequest extends ListPurchasesRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('exportPurchases', $this->center());
    }
}
