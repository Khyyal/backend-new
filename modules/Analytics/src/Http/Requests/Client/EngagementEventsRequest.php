<?php

namespace Modules\Analytics\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Analytics\Enums\CenterEngagementAction;

#[BodyParam('events', 'object[]', 'Batched events (1-50).', example: [['action' => 'service_click', 'center_id' => 8, 'service_id' => 31]])]
#[BodyParam('events[].action', 'string', '`center_click` (opened a center), `service_click` (opened one of its services), `map_click` (opened its location), `whatsapp_click` (opened its WhatsApp).', enum: ['center_click', 'service_click', 'map_click', 'whatsapp_click'], example: 'service_click')]
#[BodyParam('events[].center_id', 'integer', 'Center ID (min 1).', example: 8)]
#[BodyParam('events[].service_id', 'integer', 'Service of that center; required for `service_click`.', required: false, nullable: true, example: 31)]
class EngagementEventsRequest extends FormRequest
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
            'events.*.action' => ['required', Rule::enum(CenterEngagementAction::class)],
            'events.*.center_id' => ['required', 'integer', 'min:1'],
            'events.*.service_id' => ['nullable', 'integer', 'min:1', 'required_if:events.*.action,'.CenterEngagementAction::ServiceClick->value],
        ];
    }
}
