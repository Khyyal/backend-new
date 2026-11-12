<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('stars', 'integer', 'Stars, 1-5. Omit to keep the current value.', required: false, example: 4)]
#[BodyParam('comment', 'string', 'Comment (max 1000). Omit to keep the current one; send null to remove it.', required: false, nullable: true, example: 'Good overall.')]
class UpdateRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'stars' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stars.required' => __('validation.stars_required'),
            'stars.integer' => __('validation.stars_integer'),
            'stars.min' => __('validation.stars_min'),
            'stars.max' => __('validation.stars_max'),

            'comment.string' => __('validation.comment_string'),
            'comment.max' => __('validation.comment_max'),
        ];
    }
}
