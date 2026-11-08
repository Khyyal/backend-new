<?php

namespace Modules\Centers\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\PurchaseStatus;

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
