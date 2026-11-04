<?php

namespace Modules\Homepage\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Homepage\Enums\ContentEntityType;
use Modules\Homepage\Enums\ContentEventType;

class HomeEventsRequest extends FormRequest
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
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.type' => ['required', Rule::enum(ContentEventType::class)],
            'events.*.entity_type' => ['required', Rule::enum(ContentEntityType::class)],
            'events.*.entity_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
