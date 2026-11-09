<?php

namespace Modules\Reservations\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Center staff book a walk-in for a client: paid at the desk (`manual`), on
 * arrival, or online by the client.
 */
#[BodyParam('client_id', 'integer', 'Existing (not deleted) client.', example: 77)]
#[BodyParam('service_id', 'integer', 'Service ID (`service_id` of the service resources).', example: 31)]
#[BodyParam('date', 'string', 'Day, `Y-m-d` (first night for resorts).', example: '2026-06-05')]
#[BodyParam('start_time', 'string', '`H:i`; required for riding, training and timed visits.', required: false, nullable: true, example: '16:00')]
#[BodyParam('end_date', 'string', 'Last day of a resort stay, `Y-m-d`, on or after `date`.', required: false, nullable: true, example: 'No-example')]
#[BodyParam('trainer_id', 'integer', 'Trainer; required for training.', required: false, nullable: true, example: 'No-example')]
#[BodyParam('quantity', 'integer', 'People or tickets, 1-100. Default 1.', required: false, example: 2)]
#[BodyParam('price_option_id', 'integer', 'The duration option; required for riding and training.', required: false, nullable: true, example: 70)]
#[BodyParam('lines', 'object[]', 'Events: exactly one ticket option; resorts: any add-ons (max 20).', required: false, example: [['price_option_id' => 71, 'quantity' => 2]])]
#[BodyParam('lines[].price_option_id', 'integer', 'Distinct price option of the service.', example: 71)]
#[BodyParam('lines[].quantity', 'integer', 'Quantity, 1-100.', example: 2)]
#[BodyParam('payment_method', 'string', '`manual` (paid at the desk, confirmed at once), `cash_on_arrival` or `online`.', enum: ['online', 'cash_on_arrival', 'manual'], example: 'manual')]
#[BodyParam('payment_company', 'string', 'Gateway for online payments: `moyasar` (card), `tamara` (installments) or `fake` (dev/test only). Default: the configured gateway.', required: false, nullable: true, enum: ['moyasar', 'tamara', 'fake'], example: 'moyasar')]
#[BodyParam('coupon_code', 'string', 'Coupon to apply (max 32). A code that cannot be applied fails with 422 `code: coupon_rejected`.', required: false, nullable: true, example: 'SUMMER25')]
#[BodyParam('expected_total_amount', 'integer', 'The quoted `total_amount` in halalas; a different price fails with 409 `code: price_changed` instead of charging it.', required: false, nullable: true, example: 25500)]
#[BodyParam('notes', 'string', 'Staff notes (max 1000).', required: false, nullable: true, example: 'Prefers a calm horse.')]
class StoreReservationRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageReservations', $this->route('center'));
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            ...$this->bookingRules(),
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            ...$this->paymentCompanyRules(),
            ...$this->discountRules(),
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
