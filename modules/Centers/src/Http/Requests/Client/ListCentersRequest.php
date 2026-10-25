<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Centers\Enums\CenterOrder;
use Modules\Services\Enums\ServiceType;

/**
 * `lat`/`lng` are required together, and required when `order` is
 * `nearest`. `bounds` is a `{north, south, east, west}` box; all four are
 * required together. `services_types`/`tags` match any of the given
 * values (OR). `search` matches the center `name`.
 */
class ListCentersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng', 'required_if:order,nearest'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat', 'required_if:order,nearest'],
            'search' => ['nullable', 'string', 'max:255'],
            'services_types' => ['nullable', 'array'],
            'services_types.*' => [new Enum(ServiceType::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'bounds' => ['nullable', 'array'],
            'bounds.north' => ['required_with:bounds', 'numeric', 'between:-90,90'],
            'bounds.south' => ['required_with:bounds', 'numeric', 'between:-90,90'],
            'bounds.east' => ['required_with:bounds', 'numeric', 'between:-180,180'],
            'bounds.west' => ['required_with:bounds', 'numeric', 'between:-180,180'],
            'order' => ['nullable', new Enum(CenterOrder::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
