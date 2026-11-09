<?php

namespace Modules\Promotions\Http\Requests;

use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Promotions\Enums\CouponStatus;

#[BodyParam('count', 'integer', 'Number of codes to generate (1-10000). Up to 200 are created inline (201); more are queued (202).', example: 50)]
#[BodyParam('prefix', 'string', 'Optional prefix, 0-8 chars of A-Z, 0-9, `_` and `-` (upper-cased).', required: false, nullable: true, example: 'SUM')]
#[BodyParam('length', 'integer', 'Length of the random part (6-16). Default 8.', required: false, nullable: true, example: 8)]
#[BodyParam('usage_limit', 'integer', 'Total redemptions allowed (min 1). Omit or null for unlimited.', required: false, nullable: true, example: 500)]
#[BodyParam('per_client_usage_limit', 'integer', 'Redemptions allowed per client (min 1). Omit or null for unlimited.', required: false, nullable: true, example: 1)]
#[BodyParam('starts_at', 'string', 'Validity start (any parseable date; ISO-8601 recommended). Null falls back to the promotion window.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'Validity end; must be after `starts_at`. Null falls back to the promotion window.', required: false, nullable: true, example: '2026-06-30T23:59:59Z')]
#[BodyParam('is_assigned_only', 'boolean', 'Only assigned clients may redeem it. Default false.', required: false, example: false)]
#[BodyParam('status', 'string', 'Coupon status. Default `active`.', required: false, enum: CouponStatus::class, example: 'active')]
class GenerateCouponsRequest extends StoreCouponRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('prefix'))) {
            $this->merge(['prefix' => strtoupper(trim($this->input('prefix')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
            'prefix' => ['nullable', 'string', 'regex:/^[A-Z0-9_-]{0,8}$/'],
            'length' => ['nullable', 'integer', 'between:6,16'],
        ], $this->limitRules());
    }
}
