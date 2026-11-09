<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\Promotion;

#[BodyParam('code', 'string', 'Unique code, 4-32 chars of A-Z, 0-9, `_` and `-`. Lower-case input is upper-cased.', example: 'SUMMER25')]
#[BodyParam('usage_limit', 'integer', 'Total redemptions allowed (min 1). Omit or null for unlimited.', required: false, nullable: true, example: 500)]
#[BodyParam('per_client_usage_limit', 'integer', 'Redemptions allowed per client (min 1). Omit or null for unlimited.', required: false, nullable: true, example: 1)]
#[BodyParam('starts_at', 'string', 'Validity start (any parseable date; ISO-8601 recommended). Null falls back to the promotion window.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'Validity end; must be after `starts_at`. Null falls back to the promotion window.', required: false, nullable: true, example: '2026-06-30T23:59:59Z')]
#[BodyParam('is_assigned_only', 'boolean', 'Only assigned clients may redeem it. Default false.', required: false, example: false)]
#[BodyParam('status', 'string', 'Coupon status. Default `active`.', required: false, enum: CouponStatus::class, example: 'active')]
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
