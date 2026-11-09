<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\PurchaseStatus;

#[QueryParam('status', 'string', 'Only this purchase status.', required: false, enum: PurchaseStatus::class, example: 'confirmed')]
#[QueryParam('q', 'string', 'Part of the client\'s full name or phone (max 100).', required: false, example: 'Noura')]
#[QueryParam('service_id', 'integer', 'Only purchases with a reservation or package of this service.', required: false, example: 31)]
#[QueryParam('from', 'string', 'Created on or after this day, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Created on or before this day, `Y-m-d`, not before `from`.', required: false, example: '2026-05-31')]
#[QueryParam('page', 'integer', 'Page number (1-based); 25 per page.', required: false, example: 1)]
class ListPurchasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewPurchases', $this->center());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** `pending`, `confirmed`, `cancelled` or `completed`. */
            'status' => ['sometimes', 'string', Rule::enum(PurchaseStatus::class)],
            /** Part of the client's full name or phone number. */
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            /** Only purchases with a reservation or package of this service. */
            'service_id' => ['sometimes', 'integer'],
            /** Inclusive range on the purchase creation date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function center(): Center
    {
        return $this->route('center');
    }
}
