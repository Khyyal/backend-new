<?php

namespace Modules\Settlements\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Settlements\Enums\CenterLedgerEntryType;
use Modules\Settlements\Models\CenterPayout;

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
        ];
    }
}
