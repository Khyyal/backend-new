<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Prices a membership before buying it, with an optional coupon code.
 */
#[BodyParam('membership_id', 'integer', 'Existing membership (`id` of `MembershipResource`).', example: 3)]
#[BodyParam('coupon_code', 'string', 'Coupon to apply (max 32). A code that cannot be applied fails with 422 `code: coupon_rejected`.', required: false, nullable: true, example: 'SUMMER25')]
#[BodyParam('expected_total_amount', 'integer', 'The quoted `total_amount` in halalas; a different price fails with 409 `code: price_changed` instead of charging it.', required: false, nullable: true, example: 25500)]
class QuoteMembershipRequest extends FormRequest
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
            ...$this->discountRules(),
        ];
    }
}
