<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Prices a membership before buying it, with an optional coupon code.
 */
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
