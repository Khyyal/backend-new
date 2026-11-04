<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Homepage\Enums\ContentEntityType;

class HomeContentAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required_with:to_date', 'nullable', 'date_format:Y-m-d', 'before_or_equal:to_date'],
            'to_date' => ['required_with:from_date', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'compare' => ['sometimes', Rule::in(['prior_period', 'prior_year', 'none'])],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
            'entity_type' => ['sometimes', Rule::enum(ContentEntityType::class)],
        ];
    }
}
