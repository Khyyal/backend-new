<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Reservations\Enums\StayInvoiceKind;

/**
 * An extra charge on a confirmed stabling stay.
 */
#[BodyParam('kind', 'string', 'What is billed: a `horse_service`, a `horse_care`, or a `standalone` line.', enum: StayInvoiceKind::class, example: 'horse_service')]
#[BodyParam('service_id', 'integer', 'Horse service or horse care service of this center (matching `kind`); required unless standalone.', required: false, example: 44)]
#[BodyParam('price_option_id', 'integer', 'Price option of that service; required unless standalone.', required: false, example: 90)]
#[BodyParam('date', 'string', 'Day of the charge, `Y-m-d`, within the stay.', example: '2026-12-10')]
#[BodyParam('name', 'string', 'Name of a standalone invoice (max 255); required for standalone, ignored otherwise.', required: false, example: 'Vet visit')]
#[BodyParam('description', 'string', 'Optional details (max 2000).', required: false, nullable: true, example: 'Hoof check')]
#[BodyParam('total_amount', 'integer', 'Total in halalas: replaces the option price; required for standalone. 0 records the invoice without a purchase.', required: false, nullable: true, example: 25000)]
#[BodyParam('is_paid', 'boolean', 'true: paid at the desk (payment recorded, purchase confirmed); false: payment pending.', example: false)]
class StoreStayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('editReservations', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $standalone = StayInvoiceKind::Standalone->value;

        return [
            'kind' => ['required', Rule::enum(StayInvoiceKind::class)],
            'service_id' => ["required_unless:kind,{$standalone}", 'nullable', 'integer'],
            'price_option_id' => ["required_unless:kind,{$standalone}", 'nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ["required_if:kind,{$standalone}", 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'total_amount' => ["required_if:kind,{$standalone}", 'nullable', 'integer', 'min:0'],
            'is_paid' => ['required', 'boolean'],
        ];
    }
}
