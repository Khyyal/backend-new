<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * A client books one slot of a service and pays online or on arrival.
 */
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
#[BodyParam('payment_method', 'string', '`online` (pending until paid) or `cash_on_arrival` (confirmed at once, when the service allows it).', enum: ['online', 'cash_on_arrival'], example: 'online')]
#[BodyParam('payment_company', 'string', 'Gateway for online payments: `moyasar` (card), `tamara` (installments) or `fake` (dev/test only). Default: the configured gateway.', required: false, nullable: true, enum: ['moyasar', 'tamara', 'fake'], example: 'moyasar')]
#[BodyParam('coupon_code', 'string', 'Coupon to apply (max 32). A code that cannot be applied fails with 422 `code: coupon_rejected`.', required: false, nullable: true, example: 'SUMMER25')]
#[BodyParam('expected_total_amount', 'integer', 'The quoted `total_amount` in halalas; a different price fails with 409 `code: price_changed` instead of charging it.', required: false, nullable: true, example: 25500)]
class StoreReservationRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->bookingRules(),
            'payment_method' => ['required', Rule::in([PaymentMethod::Online->value, PaymentMethod::CashOnArrival->value])],
            ...$this->paymentCompanyRules(),
            ...$this->discountRules(),
        ];
    }
}
