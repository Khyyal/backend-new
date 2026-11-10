<?php

namespace Modules\Purchase\Http\Requests\Admin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\AdminPurchaseQuery;

/**
 * Same filters as the admin purchase list.
 */
#[BodyParam('center_id', 'integer', 'Only this center.', required: false, example: 8)]
#[BodyParam('client_id', 'integer', 'Only this client.', required: false, example: 77)]
#[BodyParam('q', 'string', 'Client full name or phone (max 100).', required: false, example: 'Noura')]
#[BodyParam('status', 'string', 'Purchase status.', required: false, enum: PurchaseStatus::class, example: 'confirmed')]
#[BodyParam('source', 'string', 'Who created it.', required: false, enum: PurchaseSource::class, example: 'client')]
#[BodyParam('channel', 'string', 'Where it was made.', required: false, enum: PurchaseChannel::class, example: 'khyyal_app')]
#[BodyParam('payment_method', 'string', 'Latest payment method.', required: false, enum: PaymentMethod::class, example: 'online')]
#[BodyParam('payment_status', 'string', 'Latest payment status.', required: false, enum: PaymentStatus::class, example: 'succeeded')]
#[BodyParam('service_id', 'integer', 'Purchases with a reservation or package of this service.', required: false, example: 31)]
#[BodyParam('from', 'string', 'Created on or after, `Y-m-d`.', required: false, example: '2026-05-01')]
#[BodyParam('to', 'string', 'Created on or before, `Y-m-d`.', required: false, example: '2026-05-31')]
#[BodyParam('sort', 'string', 'Row order, as in the list; prefix `-` for descending. Default newest first.', required: false, enum: AdminPurchaseQuery::SORTS, example: '-total_amount')]
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
