<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * Codes are immutable once issued; everything else can be adjusted.
 */
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
