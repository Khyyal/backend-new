<?php

namespace Modules\Purchase\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;

class ListPurchasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', Purchase::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'center_id' => ['sometimes', 'integer'],
            'client_id' => ['sometimes', 'integer'],
            /** Part of the client's full name or phone number; every word must match. */
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(PurchaseStatus::class)],
            /** `client`: the client paid online; `center`: created by the center staff. */
            'source' => ['sometimes', Rule::enum(PurchaseSource::class)],
            'channel' => ['sometimes', Rule::enum(PurchaseChannel::class)],
            /** Method of the latest payment. */
            'payment_method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            /** Status of the latest payment. */
            'payment_status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'service_id' => ['sometimes', 'integer'],
            /** Inclusive range on the purchase creation date (`Y-m-d`). */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
