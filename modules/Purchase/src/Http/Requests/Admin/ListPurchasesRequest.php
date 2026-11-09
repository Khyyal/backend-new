<?php

namespace Modules\Purchase\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;

#[QueryParam('center_id', 'integer', 'Only this center.', required: false, example: 8)]
#[QueryParam('client_id', 'integer', 'Only this client.', required: false, example: 77)]
#[QueryParam('q', 'string', 'Client full name or phone (max 100).', required: false, example: 'Noura')]
#[QueryParam('status', 'string', 'Purchase status.', required: false, enum: PurchaseStatus::class, example: 'confirmed')]
#[QueryParam('source', 'string', 'Who created it.', required: false, enum: PurchaseSource::class, example: 'client')]
#[QueryParam('channel', 'string', 'Where it was made.', required: false, enum: PurchaseChannel::class, example: 'khyyal_app')]
#[QueryParam('payment_method', 'string', 'Latest payment method.', required: false, enum: PaymentMethod::class, example: 'online')]
#[QueryParam('payment_status', 'string', 'Latest payment status.', required: false, enum: PaymentStatus::class, example: 'succeeded')]
#[QueryParam('service_id', 'integer', 'Purchases with a reservation or package of this service.', required: false, example: 31)]
#[QueryParam('from', 'string', 'Created on or after, `Y-m-d`.', required: false, example: '2026-05-01')]
#[QueryParam('to', 'string', 'Created on or before, `Y-m-d`.', required: false, example: '2026-05-31')]
#[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 25.', required: false, example: 25)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
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
