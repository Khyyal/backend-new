<?php

namespace Modules\Settlements\Http\Requests\Admin;

use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Services\CenterLedgerQuery;

/**
 * Same filters as the admin center ledger.
 */
#[BodyParam('type', 'string', 'Only this entry type.', required: false, enum: ['payment', 'platform_subsidy', 'subsidy_reversal', 'platform_fee', 'fee_reversal', 'refund', 'baseline_adjustment'], example: 'payment')]
#[BodyParam('paid_out', 'boolean', '`1` only entries already in a payout, `0` only unpaid ones.', required: false, example: false)]
#[BodyParam('from', 'string', 'Entries on or after, `Y-m-d`.', required: false, example: '2026-05-01')]
#[BodyParam('to', 'string', 'Entries on or before, `Y-m-d`.', required: false, example: '2026-05-31')]
#[BodyParam('sort', 'string', 'Row order, as in the list; prefix `-` for descending. Default newest first.', required: false, enum: CenterLedgerQuery::SORTS, example: '-amount')]
class ExportCenterLedgerRequest extends ListCenterLedgerRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('export', CenterPayout::class);
    }
}
