<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Centers\Http\Requests\Concerns\ParsesCenterIncludes;

/**
 * `lat`/`lng` are optional and required together; they enable `distance_km`.
 * `include` is a comma-separated list of extras (see INCLUDES).
 */
#[QueryParam('lat', 'number', 'Your latitude, to get `distance_km`. Send with `lng`.', required: false, example: 24.7)]
#[QueryParam('lng', 'number', 'Your longitude. Send with `lat`.', required: false, example: 46.68)]
#[QueryParam('include', 'string', 'Comma-separated extras. `ratings` adds the rating summary and the 10 highest ratings.', required: false, example: 'ratings')]
class ShowCenterRequest extends FormRequest
{
    use ParsesCenterIncludes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->includeRules(),
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ];
    }
}
