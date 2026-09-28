<?php

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coupons' => ['array', 'min:1'],
            'coupons.*.code' => ['required_with:coupons', 'string', 'max:64'],
            'coupons.*.starts_at' => ['required_with:coupons', 'date'],
            'coupons.*.ends_at' => ['nullable', 'date', 'after_or_equal:coupons.*.starts_at'],

            'generator' => ['array'],
            'generator.prefix' => ['required_with:generator', 'string', 'max:32'],
            'generator.quantity' => ['required_with:generator', 'integer', 'gte:1', 'lte:100000'],
            'generator.length' => ['required_with:generator', 'integer', 'gte:4', 'lte:32'],
            'generator.starts_at' => ['required_with:generator', 'date'],
            'generator.ends_at' => ['nullable', 'date', 'after_or_equal:generator.starts_at'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (
                    ! $this->filled('coupons')
                    && ! $this->filled('generator')
                ) {
                    $validator->addError(
                        'coupons',
                        __('Either coupons array or generator configuration must be provided.')
                    );
                }
            },
        ];
    }
}
