<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Centers\Enums\CenterOrder;
use Modules\Services\Enums\ServiceType;

/**
 * `lat`/`lng` are required together, and required when `order` is
 * `nearest`. `bounds` is a `{north, south, east, west}` box; all four are
 * required together. `services_types`/`tags` match any of the given
 * values (OR). `search` matches the center `name`.
 */
#[QueryParam('city_id', 'integer', 'Only centers of this city.', required: false, example: 1)]
#[QueryParam('lat', 'number', 'Your latitude; send with `lng`. Required for `order=nearest`.', required: false, example: 24.7)]
#[QueryParam('lng', 'number', 'Your longitude; send with `lat`. Required for `order=nearest`.', required: false, example: 46.68)]
#[QueryParam('search', 'string', 'Matches the center name (max 255).', required: false, example: 'Fursan')]
#[QueryParam('services_types', 'string[]', 'Centers offering any of these service types, as `services_types[]=visit`.', required: false, enum: ServiceType::class, example: ['recreation_riding', 'training'])]
#[QueryParam('tags', 'integer[]', 'Centers having any of these tag IDs, as `tags[]=4`.', required: false, example: [4])]
#[QueryParam('bounds', 'object', 'Map viewport; send all four edges together, as `bounds[north]=…`.', required: false, example: ['north' => 24.9, 'south' => 24.5, 'east' => 46.9, 'west' => 46.4])]
#[QueryParam('bounds.north', 'number', 'North latitude.', required: false, example: 24.9)]
#[QueryParam('bounds.south', 'number', 'South latitude.', required: false, example: 24.5)]
#[QueryParam('bounds.east', 'number', 'East longitude.', required: false, example: 46.9)]
#[QueryParam('bounds.west', 'number', 'West longitude.', required: false, example: 46.4)]
#[QueryParam('order', 'string', '`most_popular` (default, by points) or `nearest` (by distance to `lat`/`lng`).', required: false, enum: CenterOrder::class, example: 'most_popular')]
#[QueryParam('page', 'integer', 'Page number (1-based); 15 per page.', required: false, example: 1)]
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
