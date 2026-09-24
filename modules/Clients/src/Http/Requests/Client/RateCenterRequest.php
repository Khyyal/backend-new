<?php

namespace Modules\Clients\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class RateCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'center_id' => ['required', 'integer', 'exists:centers,id'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'center_id.required' => __('validation.center_id_required'),
            'center_id.integer' => __('validation.center_id_integer'),
            'center_id.exists' => __('validation.center_id_exists'),

            'stars.required' => __('validation.stars_required'),
            'stars.integer' => __('validation.stars_integer'),
            'stars.min' => __('validation.stars_min'),
            'stars.max' => __('validation.stars_max'),

            'comment.string' => __('validation.comment_string'),
            'comment.max' => __('validation.comment_max'),
        ];
    }
}
