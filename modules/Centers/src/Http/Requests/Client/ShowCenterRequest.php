<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `lat`/`lng` are optional and required together; they enable `distance_km`.
 */
class ShowCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ];
    }
}
