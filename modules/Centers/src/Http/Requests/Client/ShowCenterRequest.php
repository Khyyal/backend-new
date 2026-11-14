<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;

/**
 * `lat`/`lng` are optional and required together; they enable `distance_km`.
 * `include` is a comma-separated list of extra relations (see INCLUDES).
 */
#[QueryParam('lat', 'number', 'Your latitude, to get `distance_km`. Send with `lng`.', required: false, example: 24.7)]
#[QueryParam('lng', 'number', 'Your longitude. Send with `lat`.', required: false, example: 46.68)]
#[QueryParam('include', 'string', 'Comma-separated extras. `ratings` adds the rating summary and the 10 highest ratings.', required: false, example: 'ratings')]
class ShowCenterRequest extends FormRequest
{
    /**
     * Accepted `include` values.
     *
     * @var list<string>
     */
    public const array INCLUDES = ['ratings'];

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
            'include' => ['nullable', 'array'],
            'include.*' => ['string', Rule::in(self::INCLUDES)],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ];
    }

    public function includes(string $relation): bool
    {
        return in_array($relation, $this->input('include') ?? [], true);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('include'))) {
            $this->merge([
                'include' => array_values(array_filter(array_map('trim', explode(',', $this->input('include'))))),
            ]);
        }
    }
}
