<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Buys a membership; memberships are paid online.
 */
#[BodyParam('membership_id', 'integer', 'Existing membership (`id` of `MembershipResource`).', example: 3)]
#[BodyParam('payment_company', 'string', 'Gateway for online payments: `moyasar` (card), `tamara` (installments) or `fake` (dev/test only). Default: the configured gateway.', required: false, nullable: true, enum: ['moyasar', 'tamara', 'fake'], example: 'moyasar')]
#[BodyParam('coupon_code', 'string', 'Coupon to apply (max 32). A code that cannot be applied fails with 422 `code: coupon_rejected`.', required: false, nullable: true, example: 'SUMMER25')]
#[BodyParam('expected_total_amount', 'integer', 'The quoted `total_amount` in halalas; a different price fails with 409 `code: price_changed` instead of charging it.', required: false, nullable: true, example: 25500)]
class PurchaseMembershipRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_id' => ['required', 'integer', Rule::exists('memberships', 'id')->whereNull('deleted_at')],
            ...$this->paymentCompanyRules(),
            ...$this->discountRules(),
        ];
    }
}
