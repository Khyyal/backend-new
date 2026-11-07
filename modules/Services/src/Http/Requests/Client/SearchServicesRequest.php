<?php

namespace Modules\Services\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Services\Enums\ServiceType;

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
