<?php

namespace Modules\Reservations\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reservations\Http\Requests\Concerns\HasBookingRules;

/**
 * Prices a service package before buying it, with an optional coupon code.
 */
class QuotePackageRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_package_id' => ['required', 'integer', Rule::exists('service_packages', 'id')->whereNull('deleted_at')],
            ...$this->discountRules(),
        ];
    }
}
