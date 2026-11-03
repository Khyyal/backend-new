<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Coupon::normalizeCode($this->input('code'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'regex:/^[A-Z0-9_-]{4,32}$/', 'unique:coupons,code'],
        ], $this->limitRules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function limitRules(): array
    {
        return [
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_client_usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_assigned_only' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(CouponStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $promotion = $this->route('promotion');

            if ($promotion instanceof Promotion && $promotion->trigger !== PromotionTrigger::Coupon) {
                $validator->errors()->add('promotion', __('promotions::messages.not_a_coupon_promotion'));
            }
        });
    }
}
