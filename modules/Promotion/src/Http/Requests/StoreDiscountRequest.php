<?php

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Promotion\Enums\ApplicationMethod;
use Modules\Promotion\Enums\DiscountScope;
use Modules\Promotion\Enums\DiscountType;
use Modules\Promotion\Enums\PromotionStatus;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'array', 'min:1'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.ar' => ['nullable', 'string'],

            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'numeric', 'gte:0'],

            'scope' => ['required', Rule::enum(DiscountScope::class)],
            'application_method' => ['required', Rule::enum(ApplicationMethod::class)],

            'minimum_amount' => ['nullable', 'numeric', 'gte:0'],
            'maximum_discount' => ['nullable', 'numeric', 'gte:0'],

            'usage_limit' => ['nullable', 'integer', 'gte:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'gte:1'],

            'is_stackable' => ['nullable', 'boolean'],

            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'status' => ['nullable', Rule::enum(PromotionStatus::class)],
        ];
    }
}
