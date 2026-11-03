<?php

namespace Modules\Promotions\Http\Requests;

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
