<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Promotions\Enums\CouponStatus;

/**
 * Codes are immutable once issued; everything else can be adjusted.
 */
#[BodyParam('code', 'string', 'Prohibited: codes cannot change once issued.', required: false, example: 'No-example')]
#[BodyParam('usage_limit', 'integer', 'Total redemptions allowed (min 1). Omit or null for unlimited.', required: false, nullable: true, example: 500)]
#[BodyParam('per_client_usage_limit', 'integer', 'Redemptions allowed per client (min 1). Omit or null for unlimited.', required: false, nullable: true, example: 1)]
#[BodyParam('starts_at', 'string', 'Validity start (any parseable date; ISO-8601 recommended). Null falls back to the promotion window.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'Validity end; must be after `starts_at`. Null falls back to the promotion window.', required: false, nullable: true, example: '2026-06-30T23:59:59Z')]
#[BodyParam('is_assigned_only', 'boolean', 'Only assigned clients may redeem it. Default false.', required: false, example: false)]
#[BodyParam('status', 'string', 'Coupon status. Default `active`.', required: false, enum: CouponStatus::class, example: 'active')]
class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(['code' => ['prohibited']], $this->limitRules());
    }

    public function withValidator(Validator $validator): void
    {
        //
    }
}
