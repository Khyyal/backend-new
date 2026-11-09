<?php

namespace Modules\Services\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Services\Enums\ServiceType;

#[QueryParam('search', 'string', 'Matches name or description in English or Arabic (max 100).', required: false, example: 'ride')]
#[QueryParam('types', 'string[]', 'Service types, as `types[]=visit`.', required: false, enum: ServiceType::class, example: ['recreation_riding'])]
#[QueryParam('city_id', 'integer', 'Only centers of this city.', required: false, example: 1)]
#[QueryParam('center_id', 'integer', 'Only this center.', required: false, example: 8)]
#[QueryParam('per_page', 'integer', 'Items per page, 1-50. Default 15.', required: false, example: 15)]
#[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
class SearchServicesRequest extends FormRequest
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
            /** Matches the service name or description (English or Arabic). */
            'search' => ['nullable', 'string', 'max:100'],
            /** One or more service types. */
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::enum(ServiceType::class)],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
