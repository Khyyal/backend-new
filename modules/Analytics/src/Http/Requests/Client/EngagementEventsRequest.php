<?php

namespace Modules\Analytics\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Analytics\Enums\CenterEngagementAction;

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
