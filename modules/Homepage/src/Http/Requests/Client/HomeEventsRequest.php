<?php

namespace Modules\Homepage\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Homepage\Enums\ContentEntityType;
use Modules\Homepage\Enums\ContentEventType;

#[BodyParam('events', 'object[]', 'Batched events (1-50).', example: [['type' => 'impression', 'entity_type' => 'banner', 'entity_id' => 5]])]
#[BodyParam('events[].type', 'string', 'Event kind.', enum: ContentEventType::class, example: 'impression')]
#[BodyParam('events[].entity_type', 'string', 'What was seen or tapped.', enum: ContentEntityType::class, example: 'banner')]
#[BodyParam('events[].entity_id', 'integer', 'Its ID (min 1).', example: 5)]
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
