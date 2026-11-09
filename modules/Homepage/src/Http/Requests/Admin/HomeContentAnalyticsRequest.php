<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Homepage\Enums\ContentEntityType;

#[QueryParam('from_date', 'string', 'Range start `Y-m-d`; send with `to_date`. Default: 29 days before `to_date`.', required: false, example: '2026-05-01')]
#[QueryParam('to_date', 'string', 'Range end `Y-m-d`; send with `from_date`. Default: today.', required: false, example: '2026-05-30')]
#[QueryParam('compare', 'string', 'Comparison period. Default `prior_period`.', required: false, enum: ['prior_period', 'prior_year', 'none'], example: 'prior_period')]
#[QueryParam('limit', 'integer', 'Size of `top_content`, 1-50. Default 10.', required: false, example: 10)]
#[QueryParam('entity_type', 'string', 'Only this kind of content.', required: false, enum: ContentEntityType::class, example: 'banner')]
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
