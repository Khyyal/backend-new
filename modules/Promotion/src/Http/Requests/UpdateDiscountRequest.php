<?php

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Enums\PromotionStatus;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'array', 'min:1'],
            'name.en' => ['string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.ar' => ['nullable', 'string'],

            'type' => ['sometimes', Rule::enum(DiscountType::class)],
            'value' => ['sometimes', 'numeric', 'gte:0'],

            'scope' => ['sometimes', Rule::enum(DiscountScope::class)],
            'application_method' => ['sometimes', Rule::enum(ApplicationMethod::class)],

            'minimum_amount' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'maximum_discount' => ['sometimes', 'nullable', 'numeric', 'gte:0'],

            'usage_limit' => ['sometimes', 'nullable', 'integer', 'gte:1'],
            'usage_limit_per_customer' => ['sometimes', 'nullable', 'integer', 'gte:1'],

            'is_stackable' => ['sometimes', 'boolean'],

            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],

            'status' => ['sometimes', Rule::enum(PromotionStatus::class)],
        ];
    }
}
