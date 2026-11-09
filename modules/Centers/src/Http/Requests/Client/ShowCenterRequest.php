<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\QueryParam;

/**
 * `lat`/`lng` are optional and required together; they enable `distance_km`.
 */
#[QueryParam('lat', 'number', 'Your latitude, to get `distance_km`. Send with `lng`.', required: false, example: 24.7)]
#[QueryParam('lng', 'number', 'Your longitude. Send with `lat`.', required: false, example: 46.68)]
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
