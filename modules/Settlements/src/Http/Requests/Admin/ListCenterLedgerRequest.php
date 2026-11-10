<?php

namespace Modules\Settlements\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Services\CenterLedgerQuery;

#[QueryParam('type', 'string', 'Only this entry type.', required: false, enum: ['payment', 'platform_subsidy', 'subsidy_reversal', 'platform_fee', 'fee_reversal', 'refund'], example: 'payment')]
#[QueryParam('paid_out', 'boolean', '`1` only entries already in a payout, `0` only unpaid ones.', required: false, example: false)]
#[QueryParam('from', 'string', 'Entries on or after, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Entries on or before, `Y-m-d`.', required: false, example: '2026-05-31')]
#[QueryParam('sort', 'string', 'Sort column; prefix `-` for descending. Default newest first.', required: false, enum: CenterLedgerQuery::SORTS, example: '-amount')]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 25.', required: false, example: 25)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
class ListCenterLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', CenterPayout::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(CenterLedgerEntryType::class)],
            /** `true`: entries already in a payout; `false`: not paid out yet. */
            'paid_out' => ['sometimes', 'boolean'],
            /** Inclusive range on the entry date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['sometimes', 'nullable', Rule::in(CenterLedgerQuery::SORTS)],
        ];
    }
}
