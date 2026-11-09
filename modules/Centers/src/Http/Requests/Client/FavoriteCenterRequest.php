<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('center_id', 'integer', 'Existing center ID.', example: 8)]
class FavoriteCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'center_id' => ['required', 'integer', 'exists:centers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'center_id.required' => __('validation.center_id_required'),
            'center_id.integer' => __('validation.center_id_integer'),
            'center_id.exists' => __('validation.center_id_exists'),
        ];
    }
}
